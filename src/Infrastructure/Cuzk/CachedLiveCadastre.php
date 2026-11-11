<?php

declare(strict_types=1);

namespace App\Infrastructure\Cuzk;

use App\Domain\Parcel\LiveCadastre;
use App\Domain\Parcel\LiveParcelState;
use App\Infrastructure\Cache\FileCache;

final readonly class CachedLiveCadastre implements LiveCadastre
{
    public function __construct(
        private LiveCadastre $inner,
        private FileCache $cache,
        private int $ttlSeconds = 3600,
    ) {
    }

    public function fetch(int $parcelId): ?LiveParcelState
    {
        $cached = $this->cache->get("live-parcel-{$parcelId}");
        if (is_array($cached)) {
            return $cached['found'] ? new LiveParcelState(...$cached['state']) : null;
        }

        $state = $this->inner->fetch($parcelId);
        $this->cache->set("live-parcel-{$parcelId}", [
            'found' => $state !== null,
            'state' => $state === null ? null : get_object_vars($state),
        ], $this->ttlSeconds);

        return $state;
    }
}
