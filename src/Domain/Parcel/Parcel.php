<?php

declare(strict_types=1);

namespace App\Domain\Parcel;

use App\Domain\Shared\BoundingBox;
use App\Domain\Shared\CodeName;
use DateTimeImmutable;

final readonly class Parcel
{
    /**
     * @param list<string> $protections
     * @param list<BpejPart> $bpej
     */
    public function __construct(
        public int $id,
        public ParcelNumber $number,
        public CodeName $cadastralArea,
        public CodeName $municipality,
        public int $area,
        public CodeName $landType,
        public ?CodeName $landUse,
        public array $protections,
        public array $bpej,
        public ?DateTimeImmutable $validFrom,
        public BoundingBox $bbox,
        public string $geoJson,
    ) {
    }

    public function estimatedOfficialPrice(): ?float
    {
        return BpejValuation::estimate($this->bpej);
    }
}
