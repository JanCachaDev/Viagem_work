<?php

declare(strict_types=1);

namespace App\Domain\Parcel;

final readonly class LiveParcelState
{
    public function __construct(
        public string $label,
        public int $area,
        public ?string $validSince,
    ) {
    }
}
