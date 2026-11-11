<?php

declare(strict_types=1);

namespace App\Domain\Cadastre;

use App\Domain\Shared\BoundingBox;

final readonly class DatasetSummary
{
    public function __construct(
        public int $parcels,
        public int $cadastralAreas,
        public int $municipalities,
        public ?string $datasetDate,
        public ?string $importedAt,
        public ?BoundingBox $bbox,
    ) {
    }
}
