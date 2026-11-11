<?php

declare(strict_types=1);

namespace App\Application\Parcel;

use App\Domain\Cadastre\CadastreCatalog;
use App\Domain\Parcel\ParcelRepository;
use App\Domain\Shared\SearchHit;

final readonly class SearchParcels
{
    private const LIMIT = 10;
    private const MIN_AREA_NAME_LENGTH = 2;

    public function __construct(
        private ParcelRepository $parcels,
        private CadastreCatalog $catalog,
    ) {
    }

    /** @return list<SearchHit> */
    public function __invoke(string $rawQuery): array
    {
        $query = SearchQuery::parse(mb_substr($rawQuery, 0, 100));
        if ($query->isEmpty()) {
            return [];
        }

        if ($query->number !== null) {
            return $this->parcels->search($query->number, $query->areaSearchKey(), self::LIMIT);
        }

        $areaKey = (string) $query->areaSearchKey();
        if (mb_strlen($areaKey) < self::MIN_AREA_NAME_LENGTH) {
            return [];
        }

        return $this->catalog->searchCadastralAreas($areaKey, self::LIMIT);
    }
}
