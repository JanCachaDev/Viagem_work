<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\Parcel\SearchParcels;
use App\Application\Parcel\SearchQuery;
use App\Domain\Cadastre\CadastreCatalog;
use App\Domain\Cadastre\DatasetSummary;
use App\Domain\Parcel\Parcel;
use App\Domain\Parcel\ParcelNumber;
use App\Domain\Parcel\ParcelRepository;
use App\Domain\Shared\BoundingBox;
use App\Domain\Shared\SearchHit;
use PHPUnit\Framework\TestCase;

final class SearchParcelsTest extends TestCase
{
    /** @var list<array{string, mixed}> */
    private array $calls = [];

    public function testQueryWithNumberSearchesParcelsInNormalizedArea(): void
    {
        ($this->useCase())('Jičín  st. 120/3');

        self::assertSame([['parcels', 'st. 120/3', 'jicin']], $this->calls);
    }

    public function testNumberOnlySearchesWholeDistrict(): void
    {
        ($this->useCase())('1854/42');

        self::assertSame([['parcels', '1854/42', null]], $this->calls);
    }

    public function testTextOnlySearchesCadastralAreas(): void
    {
        ($this->useCase())('Železnice');

        self::assertSame([['areas', 'zeleznice']], $this->calls);
    }

    public function testTooShortOrEmptyQueryDoesNotHitStorage(): void
    {
        self::assertSame([], ($this->useCase())('  '));
        self::assertSame([], ($this->useCase())('J'));
        self::assertSame([], $this->calls);
    }

    public function testSearchQueryParsing(): void
    {
        $query = SearchQuery::parse('Lázně Bělohrad 12');
        self::assertSame('Lázně Bělohrad', $query->areaName);
        self::assertSame('12', (string) $query->number);
        self::assertSame('lazne belohrad', $query->areaSearchKey());
    }

    private function useCase(): SearchParcels
    {
        $test = $this;
        $hit = new SearchHit(SearchHit::TYPE_PARCEL, 1, 'x', 'y', new BoundingBox(0, 0, 1, 1));

        $parcels = new class ($test, $hit) implements ParcelRepository {
            public function __construct(private SearchParcelsTest $test, private SearchHit $hit)
            {
            }

            public function find(int $id): ?Parcel
            {
                return null;
            }

            public function search(ParcelNumber $number, ?string $areaNamePrefix, int $limit): array
            {
                $this->test->record(['parcels', (string) $number, $areaNamePrefix]);

                return [$this->hit];
            }
        };

        $catalog = new class ($test) implements CadastreCatalog {
            public function __construct(private SearchParcelsTest $test)
            {
            }

            public function landTypes(): array
            {
                return [];
            }

            public function searchCadastralAreas(string $namePrefix, int $limit): array
            {
                $this->test->record(['areas', $namePrefix]);

                return [];
            }

            public function summary(): DatasetSummary
            {
                return new DatasetSummary(0, 0, 0, null, null, null);
            }
        };

        return new SearchParcels($parcels, $catalog);
    }

    /** @param array{string, mixed} $call */
    public function record(array $call): void
    {
        $this->calls[] = $call;
    }
}
