<?php

declare(strict_types=1);

namespace App\Application\Tile;

use App\Domain\Tile\TileCache;
use App\Domain\Tile\TileCoordinate;
use App\Domain\Tile\TileProvider;

final readonly class GetTile
{
    public function __construct(
        private TileProvider $provider,
        private TileCache $cache,
    ) {
    }

    public function __invoke(TileCoordinate $tile): string
    {
        $cached = $this->cache->get($tile);
        if ($cached !== null) {
            return $cached;
        }

        $data = $this->provider->render($tile);
        $this->cache->put($tile, $data);

        return $data;
    }
}
