<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Tile\TileCoordinate;
use App\Domain\Tile\TileProvider;
use App\Domain\Tile\ZoomLevels;
use PDO;

/**
 * Vektorové dlaždice přes ST_AsMVT. Pod ZoomLevels::FULL_DETAIL se vynechávají parcely
 * menší než ~2×2 px, geometrie se zjednodušuje na 1 px a souřadnice mají nižší rozlišení.
 * Čísla parcel jsou jen v nejvyšším zoomu.
 */
final readonly class PostGisTileProvider implements TileProvider
{
    private const EXTENT_DETAILED = 4096;
    private const EXTENT_GENERALIZED = 1024;
    private const BUFFER_PX = 8;
    private const MIN_PARCEL_SIZE_PX = 2;
    private const WORLD_SIZE = 40075016.686;
    private const GROUND_SCALE = 0.637; // cos(50.4°) – zkreslení Web Mercatoru v okrese Jičín

    public function __construct(private PDO $db)
    {
    }

    public function render(TileCoordinate $tile): string
    {
        $tileSize = self::WORLD_SIZE / (1 << $tile->z);
        $pixel = $tileSize / 512;
        $detailed = $tile->z >= ZoomLevels::FULL_DETAIL;
        $extent = $detailed ? self::EXTENT_DETAILED : self::EXTENT_GENERALIZED;
        $buffer = self::BUFFER_PX * $extent / 512;
        $minArea = $detailed ? 0 : (int) round((self::MIN_PARCEL_SIZE_PX * $pixel * self::GROUND_SCALE) ** 2);

        $statement = $this->db->prepare('
            WITH env AS (
                SELECT ST_TileEnvelope(:z, :x, :y) AS tile,
                       ST_TileEnvelope(:z, :x, :y, margin => :margin::float8) AS buffered
            ),
            parcel_features AS (
                SELECT p.id,
                       p.land_type AS t,
                       CASE WHEN :labels THEN
                           CASE WHEN p.building_plot THEN \'st. \' ELSE \'\' END
                           || p.stem || coalesce(\'/\' || p.sub, \'\')
                       END AS n,
                       ST_AsMVTGeom(
                           CASE WHEN :parcel_tolerance::float8 > 0 THEN ST_Simplify(p.geom, :parcel_tolerance::float8) ELSE p.geom END,
                           env.tile, :extent, :buffer, true
                       ) AS geom
                  FROM parcels p, env
                 WHERE :with_parcels
                   AND p.geom && env.buffered
                   AND p.area >= :min_area
            ),
            area_features AS (
                SELECT c.code AS id, c.name,
                       ST_AsMVTGeom(ST_Simplify(c.geom, :area_tolerance::float8), env.tile, :extent, :buffer, true) AS geom
                  FROM cadastral_areas c, env
                 WHERE c.geom && env.buffered
            ),
            label_features AS (
                SELECT c.code AS id, c.name,
                       ST_AsMVTGeom(c.label_point, env.tile, :extent, 0, true) AS geom
                  FROM cadastral_areas c, env
                 WHERE c.label_point && env.tile
            ),
            district_features AS (
                SELECT d.id, d.name,
                       ST_AsMVTGeom(ST_Simplify(d.geom, :area_tolerance::float8), env.tile, :extent, :buffer, true) AS geom
                  FROM district d, env
                 WHERE d.geom && env.buffered
            )
            SELECT
                coalesce((SELECT ST_AsMVT(f.*, \'parcels\', :extent, \'geom\', \'id\') FROM parcel_features f WHERE f.geom IS NOT NULL), \'\')
             || coalesce((SELECT ST_AsMVT(f.*, \'cadastral_areas\', :extent, \'geom\', \'id\') FROM area_features f WHERE f.geom IS NOT NULL), \'\')
             || coalesce((SELECT ST_AsMVT(f.*, \'cadastral_labels\', :extent, \'geom\', \'id\') FROM label_features f WHERE f.geom IS NOT NULL), \'\')
             || coalesce((SELECT ST_AsMVT(f.*, \'district\', :extent, \'geom\', \'id\') FROM district_features f WHERE f.geom IS NOT NULL), \'\')
               AS mvt
        ');
        $statement->bindValue('z', $tile->z, PDO::PARAM_INT);
        $statement->bindValue('x', $tile->x, PDO::PARAM_INT);
        $statement->bindValue('y', $tile->y, PDO::PARAM_INT);
        $statement->bindValue('margin', $buffer / $extent);
        $statement->bindValue('extent', $extent, PDO::PARAM_INT);
        $statement->bindValue('buffer', $buffer, PDO::PARAM_INT);
        $statement->bindValue('with_parcels', $tile->z >= ZoomLevels::MIN_PARCEL, PDO::PARAM_BOOL);
        $statement->bindValue('labels', $tile->z >= ZoomLevels::MAX, PDO::PARAM_BOOL);
        $statement->bindValue('min_area', $minArea, PDO::PARAM_INT);
        $statement->bindValue('parcel_tolerance', $detailed ? 0.0 : $pixel);
        $statement->bindValue('area_tolerance', $pixel);
        $statement->execute();

        $mvt = $statement->fetchColumn();

        return is_resource($mvt) ? (string) stream_get_contents($mvt) : (string) $mvt;
    }
}
