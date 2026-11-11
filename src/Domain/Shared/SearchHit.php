<?php

declare(strict_types=1);

namespace App\Domain\Shared;

final readonly class SearchHit
{
    public const TYPE_PARCEL = 'parcel';
    public const TYPE_CADASTRAL_AREA = 'cadastral_area';

    public function __construct(
        public string $type,
        public int $id,
        public string $label,
        public string $detail,
        public BoundingBox $bbox,
    ) {
    }
}
