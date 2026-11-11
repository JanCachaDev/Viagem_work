<?php

declare(strict_types=1);

namespace App\Domain\Cadastre;

use App\Domain\Shared\CodeName;
use App\Domain\Shared\SearchHit;

interface CadastreCatalog
{
    /** @return list<CodeName> */
    public function landTypes(): array;

    /** @return list<SearchHit> */
    public function searchCadastralAreas(string $namePrefix, int $limit): array;

    public function summary(): DatasetSummary;
}
