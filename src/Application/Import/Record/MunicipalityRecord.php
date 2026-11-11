<?php

declare(strict_types=1);

namespace App\Application\Import\Record;

final readonly class MunicipalityRecord
{
    public function __construct(
        public int $code,
        public string $name,
        public ?int $districtCode,
    ) {
    }
}
