<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain\Cadastre\CadastreCatalog;
use App\Http\Response;
use App\Domain\Tile\ZoomLevels;

final readonly class MetaController
{
    public function __construct(private CadastreCatalog $catalog)
    {
    }

    public function show(): Response
    {
        $summary = $this->catalog->summary();

        return Response::json([
            'landTypes' => array_map(fn ($t) => ['code' => $t->code, 'name' => $t->name], $this->catalog->landTypes()),
            'parcels' => $summary->parcels,
            'cadastralAreas' => $summary->cadastralAreas,
            'municipalities' => $summary->municipalities,
            'datasetDate' => $summary->datasetDate,
            'importedAt' => $summary->importedAt,
            'bbox' => $summary->bbox?->toArray(),
            'tiles' => [
                'maxZoom' => ZoomLevels::MAX,
                'minParcelZoom' => ZoomLevels::MIN_PARCEL,
            ],
        ], maxAge: 300);
    }
}
