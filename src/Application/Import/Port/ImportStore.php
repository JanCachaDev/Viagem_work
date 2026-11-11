<?php

declare(strict_types=1);

namespace App\Application\Import\Port;

use App\Application\Import\Record\CadastralAreaRecord;
use App\Application\Import\Record\CodeLists;
use App\Application\Import\Record\MunicipalityRecord;
use App\Application\Import\Record\ParcelRecord;

interface ImportStore
{
    public function saveCodeLists(CodeLists $codeLists): void;

    /**
     * Nahradí všechna data obce (idempotentní, v jedné transakci).
     *
     * @param iterable<CadastralAreaRecord|ParcelRecord> $content
     * @return int počet uložených parcel
     */
    public function replaceMunicipality(MunicipalityRecord $municipality, iterable $content): int;

    public function finish(string $districtName, string $datasetDate): void;
}
