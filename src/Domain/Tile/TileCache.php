<?php

declare(strict_types=1);

namespace App\Domain\Tile;

interface TileCache
{
    public function get(TileCoordinate $tile): ?string;

    public function put(TileCoordinate $tile, string $data): void;

    public function clear(): void;
}
