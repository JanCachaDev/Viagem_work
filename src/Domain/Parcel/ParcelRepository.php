<?php

declare(strict_types=1);

namespace App\Domain\Parcel;

use App\Domain\Shared\SearchHit;

interface ParcelRepository
{
    public function find(int $id): ?Parcel;

    /** @return list<SearchHit> */
    public function search(ParcelNumber $number, ?string $areaNamePrefix, int $limit): array;
}
