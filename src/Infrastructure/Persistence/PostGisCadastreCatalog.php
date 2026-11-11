<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Cadastre\CadastreCatalog;
use App\Domain\Cadastre\DatasetSummary;
use App\Domain\Shared\CodeName;
use App\Domain\Shared\SearchHit;
use PDO;

final readonly class PostGisCadastreCatalog implements CadastreCatalog
{
    public function __construct(private PDO $db)
    {
    }

    public function landTypes(): array
    {
        $rows = $this->db->query('SELECT code, name FROM land_types ORDER BY code')->fetchAll();

        return array_map(fn (array $row) => new CodeName((int) $row['code'], $row['name']), $rows);
    }

    public function searchCadastralAreas(string $namePrefix, int $limit): array
    {
        $bbox = BboxSql::select('c.geom');
        $statement = $this->db->prepare("
            SELECT c.code, c.name, m.name AS municipality_name, {$bbox} AS bbox
              FROM cadastral_areas c
              JOIN municipalities m ON m.code = c.municipality_code
             WHERE c.search_name LIKE :prefix || '%'
             ORDER BY c.name
             LIMIT :limit
        ");
        $statement->bindValue('prefix', addcslashes($namePrefix, '%_\\'));
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row) => new SearchHit(
            SearchHit::TYPE_CADASTRAL_AREA,
            (int) $row['code'],
            "k.ú. {$row['name']}",
            "obec {$row['municipality_name']}",
            BboxSql::parse($row['bbox']),
        ), $statement->fetchAll());
    }

    public function summary(): DatasetSummary
    {
        $bbox = BboxSql::select('d.geom');
        $row = $this->db->query("
            SELECT (SELECT json_object_agg(key, value) FROM meta) AS meta,
                   (SELECT {$bbox} FROM district d LIMIT 1) AS bbox
        ")->fetch();
        $meta = json_decode($row['meta'] ?? '{}', true) ?? [];

        return new DatasetSummary(
            (int) ($meta['parcels'] ?? 0),
            (int) ($meta['cadastral_areas'] ?? 0),
            (int) ($meta['municipalities'] ?? 0),
            $meta['dataset_date'] ?? null,
            $meta['imported_at'] ?? null,
            $row['bbox'] === null ? null : BboxSql::parse($row['bbox']),
        );
    }
}
