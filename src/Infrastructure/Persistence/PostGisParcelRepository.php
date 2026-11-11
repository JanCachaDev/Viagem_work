<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Parcel\BpejPart;
use App\Domain\Parcel\Parcel;
use App\Domain\Parcel\ParcelNumber;
use App\Domain\Parcel\ParcelRepository;
use App\Domain\Shared\CodeName;
use App\Domain\Shared\SearchHit;
use DateTimeImmutable;
use PDO;

final readonly class PostGisParcelRepository implements ParcelRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function find(int $id): ?Parcel
    {
        $bbox = BboxSql::select('p.geom');
        $statement = $this->db->prepare("
            SELECT p.id, p.stem, p.sub, p.building_plot, p.area, p.valid_from,
                   c.code AS area_code, c.name AS area_name,
                   m.code AS municipality_code, m.name AS municipality_name,
                   p.land_type, lt.name AS land_type_name,
                   p.land_use, lu.name AS land_use_name,
                   (SELECT coalesce(json_agg(pr.name ORDER BY pr.name), '[]')
                      FROM protections pr WHERE pr.code = ANY (p.protections)) AS protections,
                   (SELECT coalesce(json_agg(json_build_object(
                              'code', part->>'code', 'area', (part->>'area')::int, 'price', bp.price_per_m2)), '[]')
                      FROM jsonb_array_elements(p.bpej) part
                      LEFT JOIN bpej_prices bp ON bp.code = part->>'code') AS bpej,
                   {$bbox} AS bbox,
                   ST_AsGeoJSON(ST_Transform(p.geom, 4326), 7) AS geojson
              FROM parcels p
              JOIN cadastral_areas c ON c.code = p.cadastral_area_code
              JOIN municipalities m ON m.code = c.municipality_code
              LEFT JOIN land_types lt ON lt.code = p.land_type
              LEFT JOIN land_uses lu ON lu.code = p.land_use
             WHERE p.id = :id
        ");
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function search(ParcelNumber $number, ?string $areaNamePrefix, int $limit): array
    {
        $bbox = BboxSql::select('p.geom');
        $sql = "
            SELECT p.id, p.stem, p.sub, p.building_plot, c.name AS area_name, m.name AS municipality_name,
                   {$bbox} AS bbox
              FROM parcels p
              JOIN cadastral_areas c ON c.code = p.cadastral_area_code
              JOIN municipalities m ON m.code = c.municipality_code
             WHERE p.stem = :stem
               AND p.building_plot = :building
               AND (:sub::int IS NULL OR p.sub = :sub::int)
               AND (:area::text IS NULL OR c.search_name LIKE :area::text || '%')
             ORDER BY c.name, p.sub NULLS FIRST
             LIMIT :limit
        ";
        $statement = $this->db->prepare($sql);
        $statement->bindValue('stem', $number->stem, PDO::PARAM_INT);
        $statement->bindValue('building', $number->buildingPlot, PDO::PARAM_BOOL);
        $statement->bindValue('sub', $number->sub, $number->sub === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue('area', $areaNamePrefix === null ? null : self::escapeLike($areaNamePrefix));
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row) => new SearchHit(
            SearchHit::TYPE_PARCEL,
            (int) $row['id'],
            (string) new ParcelNumber((int) $row['stem'], $row['sub'] === null ? null : (int) $row['sub'], (bool) $row['building_plot']),
            "k.ú. {$row['area_name']}, obec {$row['municipality_name']}",
            BboxSql::parse($row['bbox']),
        ), $statement->fetchAll());
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Parcel
    {
        $bpej = array_map(
            fn (array $part) => new BpejPart($part['code'], $part['area'], $part['price'] === null ? null : (float) $part['price']),
            json_decode($row['bpej'], true),
        );

        return new Parcel(
            id: (int) $row['id'],
            number: new ParcelNumber((int) $row['stem'], $row['sub'] === null ? null : (int) $row['sub'], (bool) $row['building_plot']),
            cadastralArea: new CodeName((int) $row['area_code'], $row['area_name']),
            municipality: new CodeName((int) $row['municipality_code'], $row['municipality_name']),
            area: (int) $row['area'],
            landType: new CodeName((int) $row['land_type'], $row['land_type_name'] ?? "kód {$row['land_type']}"),
            landUse: $row['land_use'] === null
                ? null
                : new CodeName((int) $row['land_use'], $row['land_use_name'] ?? "kód {$row['land_use']}"),
            protections: json_decode($row['protections'], true),
            bpej: $bpej,
            validFrom: $row['valid_from'] === null ? null : new DateTimeImmutable($row['valid_from']),
            bbox: BboxSql::parse($row['bbox']),
            geoJson: $row['geojson'],
        );
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
