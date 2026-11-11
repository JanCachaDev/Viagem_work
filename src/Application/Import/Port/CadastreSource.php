<?php

declare(strict_types=1);

namespace App\Application\Import\Port;

use App\Application\Import\Record\CadastralAreaRecord;
use App\Application\Import\Record\MunicipalityRecord;
use App\Application\Import\Record\ParcelRecord;

interface CadastreSource
{
    public function datasetDate(): string;

    /** @return list<MunicipalityRecord> */
    public function municipalitiesInDistrict(int $districtCode): array;

    /** @return iterable<CadastralAreaRecord|ParcelRecord> */
    public function municipalityContent(int $municipalityCode): iterable;
}
