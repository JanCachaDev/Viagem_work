<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\Import\ImportDistrict;
use App\Application\Import\Port\CadastreSource;
use App\Application\Import\Port\CodeListSource;
use App\Application\Import\Port\ImportStore;
use App\Application\Import\Record\CodeLists;
use App\Application\Import\Record\MunicipalityRecord;
use App\Application\Import\Record\ParcelRecord;
use App\Domain\Tile\TileCache;
use App\Domain\Tile\TileCoordinate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ImportDistrictTest extends TestCase
{
    private ImportStore $store;
    private TileCache $tileCache;

    protected function setUp(): void
    {
        $this->store = new class implements ImportStore {
            /** @var list<int> */
            public array $municipalities = [];
            public ?string $finishedWith = null;
            public bool $codeListsSaved = false;

            public function saveCodeLists(CodeLists $codeLists): void
            {
                $this->codeListsSaved = true;
            }

            public function replaceMunicipality(MunicipalityRecord $municipality, iterable $content): int
            {
                $this->municipalities[] = $municipality->code;

                return count(iterator_to_array($content, false));
            }

            public function finish(string $districtName, string $datasetDate): void
            {
                $this->finishedWith = "{$districtName}@{$datasetDate}";
            }
        };
        $this->tileCache = new class implements TileCache {
            public bool $cleared = false;

            public function get(TileCoordinate $tile): ?string
            {
                return null;
            }

            public function put(TileCoordinate $tile, string $data): void
            {
            }

            public function clear(): void
            {
                $this->cleared = true;
            }
        };
    }

    public function testImportsWholeDistrictAndClearsTileCache(): void
    {
        $total = ($this->importer())(3604, 'Jičín', [], fn () => null);

        self::assertSame(3, $total);
        self::assertTrue($this->store->codeListsSaved);
        self::assertSame([1, 2], $this->store->municipalities);
        self::assertSame('Jičín@20261031', $this->store->finishedWith);
        self::assertTrue($this->tileCache->cleared);
    }

    public function testImportsOnlySelectedMunicipalities(): void
    {
        ($this->importer())(3604, 'Jičín', [2], fn () => null);

        self::assertSame([2], $this->store->municipalities);
    }

    public function testRejectsMunicipalityOutsideDistrict(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ($this->importer())(3604, 'Jičín', [999], fn () => null);
    }

    private function importer(): ImportDistrict
    {
        $source = new class implements CadastreSource {
            public function datasetDate(): string
            {
                return '20261031';
            }

            public function municipalitiesInDistrict(int $districtCode): array
            {
                return [new MunicipalityRecord(1, 'A', $districtCode), new MunicipalityRecord(2, 'B', $districtCode)];
            }

            public function municipalityContent(int $municipalityCode): iterable
            {
                for ($i = 0; $i < ($municipalityCode === 1 ? 2 : 1); $i++) {
                    yield new ParcelRecord($i, 1, 1, null, false, 1, 2, null, [], [], null, 'POLYGON EMPTY');
                }
            }
        };
        $codeLists = new class implements CodeListSource {
            public function load(): CodeLists
            {
                return new CodeLists([], [], [], []);
            }
        };

        return new ImportDistrict($source, $codeLists, $this->store, $this->tileCache);
    }
}
