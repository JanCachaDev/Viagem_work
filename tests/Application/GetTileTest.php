<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\Tile\GetTile;
use App\Domain\Tile\TileCache;
use App\Domain\Tile\TileCoordinate;
use App\Domain\Tile\TileProvider;
use PHPUnit\Framework\TestCase;

final class GetTileTest extends TestCase
{
    public function testRendersOnceAndThenServesFromCache(): void
    {
        $provider = new class implements TileProvider {
            public int $renders = 0;

            public function render(TileCoordinate $tile): string
            {
                $this->renders++;

                return "mvt:{$tile}";
            }
        };
        $cache = new class implements TileCache {
            /** @var array<string, string> */
            public array $tiles = [];

            public function get(TileCoordinate $tile): ?string
            {
                return $this->tiles[(string) $tile] ?? null;
            }

            public function put(TileCoordinate $tile, string $data): void
            {
                $this->tiles[(string) $tile] = $data;
            }

            public function clear(): void
            {
                $this->tiles = [];
            }
        };

        $getTile = new GetTile($provider, $cache);
        $tile = new TileCoordinate(14, 8891, 5524);

        self::assertSame('mvt:14/8891/5524', $getTile($tile));
        self::assertSame('mvt:14/8891/5524', $getTile($tile));
        self::assertSame(1, $provider->renders);
    }
}
