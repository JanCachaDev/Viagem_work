<?php

declare(strict_types=1);

namespace App\Application\Import\Record;

final readonly class ParcelRecord
{
    /**
     * @param list<int> $protections
     * @param list<array{code: string, area: int}> $bpej
     */
    public function __construct(
        public int $id,
        public int $cadastralAreaCode,
        public int $stem,
        public ?int $sub,
        public bool $buildingPlot,
        public int $area,
        public int $landType,
        public ?int $landUse,
        public array $protections,
        public array $bpej,
        public ?string $validFrom,
        public string $wkt,
    ) {
    }
}
