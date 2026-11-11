<?php

declare(strict_types=1);

namespace App\Application\Tile;

use App\Domain\Shared\BoundingBox;
use App\Domain\Tile\TileCoordinate;
use Closure;

final readonly class WarmTileCache
{
    public function __construct(private GetTile $getTile)
    {
    }

    /** @param Closure(int $zoom, int $done, int $total): void $progress */
    public function __invoke(BoundingBox $bbox, int $minZoom, int $maxZoom, Closure $progress): int
    {
        $count = 0;
        for ($z = $minZoom; $z <= $maxZoom; $z++) {
            $from = TileCoordinate::fromLonLat($bbox->west, $bbox->north, $z);
            $to = TileCoordinate::fromLonLat($bbox->east, $bbox->south, $z);
            $total = ($to->x - $from->x + 1) * ($to->y - $from->y + 1);
            $done = 0;
            for ($x = $from->x; $x <= $to->x; $x++) {
                for ($y = $from->y; $y <= $to->y; $y++) {
                    ($this->getTile)(new TileCoordinate($z, $x, $y));
                    $done++;
                    $count++;
                }
                $progress($z, $done, $total);
            }
        }

        return $count;
    }
}
