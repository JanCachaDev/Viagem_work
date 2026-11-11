<?php

declare(strict_types=1);

namespace App\Domain\Parcel;

final readonly class BpejPart
{
    public function __construct(
        public string $code,
        public int $area,
        public ?float $pricePerM2,
    ) {
    }

    public function price(): ?float
    {
        return $this->pricePerM2 === null ? null : $this->area * $this->pricePerM2;
    }
}
