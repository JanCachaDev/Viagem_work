<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Import\Port\ImportStore;
use App\Application\Import\Record\CadastralAreaRecord;
use App\Application\Import\Record\CodeLists;
use App\Application\Import\Record\MunicipalityRecord;
use App\Application\Import\Record\ParcelRecord;
use App\Domain\Shared\Text;
use PDO;
use PDOStatement;
use Throwable;

final class PostGisImportStore implements ImportStore
{
    private const BATCH_SIZE = 500;
    private const SOURCE_SRID = 5514;

    /** S-JTSK s oblouky → validní MultiPolygon ve Web Mercatoru. */
    private const GEOMETRY_SQL = 'ST_Multi(ST_CollectionExtract(ST_MakeValid(ST_Transform(
        ST_CurveToLine(ST_GeomFromText(%s, ' . self::SOURCE_SRID . ')), 3857)), 3))';

    /** @var array<int, PDOStatement> */
    private array $parcelInserts = [];

    public function __construct(private readonly PDO $db)
    {
    }

    public function saveCodeLists(CodeLists $codeLists): void
    {
        $this->transaction(function () use ($codeLists): void {
            $this->db->exec('TRUNCATE land_types, land_uses, protections, bpej_prices');

            $insert = $this->db->prepare('INSERT INTO land_types (code, name, agricultural) VALUES (?, ?, ?)');
            foreach ($codeLists->landTypes as $code => $type) {
                $insert->bindValue(1, $code, PDO::PARAM_INT);
                $insert->bindValue(2, $type['name']);
                $insert->bindValue(3, $type['agricultural'], PDO::PARAM_BOOL);
                $insert->execute();
            }

            $this->insertPairs('INSERT INTO land_uses (code, name) VALUES (?, ?)', $codeLists->landUses);
            $this->insertPairs('INSERT INTO protections (code, name) VALUES (?, ?)', $codeLists->protections);
            $this->insertPairs('INSERT INTO bpej_prices (code, price_per_m2) VALUES (?, ?)', $codeLists->bpejPrices);
        });
    }

    public function replaceMunicipality(MunicipalityRecord $municipality, iterable $content): int
    {
        return $this->transaction(function () use ($municipality, $content): int {
            $this->db->prepare('
                INSERT INTO municipalities (code, name) VALUES (:code, :name)
                ON CONFLICT (code) DO UPDATE SET name = excluded.name
            ')->execute(['code' => $municipality->code, 'name' => $municipality->name]);

            $this->db->prepare('
                DELETE FROM parcels WHERE cadastral_area_code IN
                    (SELECT code FROM cadastral_areas WHERE municipality_code = :code)
            ')->execute(['code' => $municipality->code]);
            $this->db->prepare('DELETE FROM cadastral_areas WHERE municipality_code = :code')
                ->execute(['code' => $municipality->code]);

            $count = 0;
            $batch = [];
            foreach ($content as $record) {
                if ($record instanceof CadastralAreaRecord) {
                    $this->insertCadastralArea($record);
                } elseif ($record instanceof ParcelRecord) {
                    $batch[] = $record;
                    if (count($batch) === self::BATCH_SIZE) {
                        $count += $this->insertParcels($batch);
                        $batch = [];
                    }
                }
            }

            return $count + $this->insertParcels($batch);
        });
    }

    public function finish(string $districtName, string $datasetDate): void
    {
        $this->transaction(function () use ($districtName, $datasetDate): void {
            $this->db->exec('DELETE FROM district');
            $this->db->prepare('
                INSERT INTO district (id, name, geom)
                SELECT 1, :name, ST_Multi(ST_CollectionExtract(ST_MakeValid(ST_Union(ST_SnapToGrid(geom, 0.05))), 3))
                  FROM cadastral_areas
                HAVING count(*) > 0
            ')->execute(['name' => $districtName]);

            $upsert = $this->db->prepare('
                INSERT INTO meta (key, value) VALUES (:key, :value)
                ON CONFLICT (key) DO UPDATE SET value = excluded.value
            ');
            $counts = $this->db->query('
                SELECT (SELECT count(*) FROM parcels) AS parcels,
                       (SELECT count(*) FROM cadastral_areas) AS cadastral_areas,
                       (SELECT count(*) FROM municipalities) AS municipalities
            ')->fetch();
            $values = [...$counts, 'dataset_date' => $datasetDate, 'imported_at' => date(DATE_ATOM)];
            foreach ($values as $key => $value) {
                $upsert->execute(['key' => $key, 'value' => (string) $value]);
            }
        });
        $this->db->exec('ANALYZE');
    }

    private function insertCadastralArea(CadastralAreaRecord $area): void
    {
        $geometry = sprintf(self::GEOMETRY_SQL, ':wkt');
        $this->db->prepare("
            INSERT INTO cadastral_areas (code, name, search_name, municipality_code, geom, label_point)
            SELECT :code, :name, :search_name, :municipality, g, ST_PointOnSurface(g)
              FROM (SELECT {$geometry} AS g) geometry
        ")->execute([
            'code' => $area->code,
            'name' => $area->name,
            'search_name' => Text::searchKey($area->name),
            'municipality' => $area->municipalityCode,
            'wkt' => $area->wkt,
        ]);
    }

    /** @param list<ParcelRecord> $parcels */
    private function insertParcels(array $parcels): int
    {
        if ($parcels === []) {
            return 0;
        }

        $statement = $this->parcelInsert(count($parcels));
        $i = 1;
        foreach ($parcels as $p) {
            $statement->bindValue($i++, $p->id, PDO::PARAM_INT);
            $statement->bindValue($i++, $p->cadastralAreaCode, PDO::PARAM_INT);
            $statement->bindValue($i++, $p->stem, PDO::PARAM_INT);
            $statement->bindValue($i++, $p->sub, $p->sub === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $statement->bindValue($i++, $p->buildingPlot, PDO::PARAM_BOOL);
            $statement->bindValue($i++, $p->area, PDO::PARAM_INT);
            $statement->bindValue($i++, $p->landType, PDO::PARAM_INT);
            $statement->bindValue($i++, $p->landUse, $p->landUse === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $statement->bindValue($i++, '{' . implode(',', $p->protections) . '}');
            $statement->bindValue($i++, json_encode($p->bpej, JSON_THROW_ON_ERROR));
            $statement->bindValue($i++, $p->validFrom, $p->validFrom === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statement->bindValue($i++, $p->wkt);
        }
        $statement->execute();

        return count($parcels);
    }

    private function parcelInsert(int $rows): PDOStatement
    {
        if (!isset($this->parcelInserts[$rows])) {
            $row = '(?::bigint, ?::int, ?::int, ?::int, ?::boolean, ?::int, ?::smallint, ?::smallint, ?::smallint[], ?::jsonb, ?::date, ?::text)';
            $values = implode(',', array_fill(0, $rows, $row));
            $geometry = sprintf(self::GEOMETRY_SQL, 'v.wkt');
            $this->parcelInserts[$rows] = $this->db->prepare("
                INSERT INTO parcels (id, cadastral_area_code, stem, sub, building_plot, area, land_type, land_use,
                                     protections, bpej, valid_from, geom)
                SELECT v.id, v.ku, v.stem, v.sub, v.building_plot, v.area, v.land_type, v.land_use,
                       v.protections, v.bpej, v.valid_from, {$geometry}
                  FROM (VALUES {$values}) AS v(id, ku, stem, sub, building_plot, area, land_type, land_use,
                                              protections, bpej, valid_from, wkt)
            ");
        }

        return $this->parcelInserts[$rows];
    }

    /** @param array<int|string, string|float> $pairs */
    private function insertPairs(string $sql, array $pairs): void
    {
        $insert = $this->db->prepare($sql);
        foreach ($pairs as $code => $value) {
            $insert->execute([$code, $value]);
        }
    }

    /**
     * @template T
     * @param callable(): T $work
     * @return T
     */
    private function transaction(callable $work): mixed
    {
        $this->db->beginTransaction();
        try {
            $result = $work();
            $this->db->commit();

            return $result;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
