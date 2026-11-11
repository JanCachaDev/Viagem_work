<?php

declare(strict_types=1);

namespace App\Domain\Tile;

interface TileProvider
{
    /** Vrací binární Mapbox Vector Tile (prázdný řetězec = prázdná dlaždice). */
    public function render(TileCoordinate $tile): string;
}
