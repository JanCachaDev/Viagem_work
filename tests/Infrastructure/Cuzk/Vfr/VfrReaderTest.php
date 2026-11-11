<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Cuzk\Vfr;

use App\Application\Import\Record\CadastralAreaRecord;
use App\Application\Import\Record\MunicipalityRecord;
use App\Application\Import\Record\ParcelRecord;
use App\Infrastructure\Cuzk\Vfr\VfrReader;
use PHPUnit\Framework\TestCase;

final class VfrReaderTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../../fixtures/vfr-obec.xml';

    public function testReadsAllRecordTypesAndSkipsSameNamedContainer(): void
    {
        $records = iterator_to_array((new VfrReader())->read(self::FIXTURE), false);

        self::assertCount(4, $records);
        self::assertInstanceOf(MunicipalityRecord::class, $records[0]);
        self::assertInstanceOf(CadastralAreaRecord::class, $records[1]);
        self::assertInstanceOf(ParcelRecord::class, $records[2]);
        self::assertSame(3604, $records[0]->districtCode);
        self::assertSame('Jičín', $records[1]->name);
        self::assertStringStartsWith('MULTISURFACE(', $records[1]->wkt);
    }

    public function testParcelAttributes(): void
    {
        $parcels = iterator_to_array((new VfrReader())->read(self::FIXTURE, [VfrReader::PARCEL]), false);

        [$field, $building] = $parcels;
        self::assertSame(100, $field->id);
        self::assertSame(659541, $field->cadastralAreaCode);
        self::assertSame(1854, $field->stem);
        self::assertSame(42, $field->sub);
        self::assertFalse($field->buildingPlot);
        self::assertSame(1537, $field->area);
        self::assertSame(2, $field->landType);
        self::assertSame(23, $field->landUse);
        self::assertSame([27], $field->protections);
        self::assertSame([['code' => '01000', 'area' => 1537]], $field->bpej);
        self::assertSame('2025-01-10', $field->validFrom);

        self::assertTrue($building->buildingPlot);
        self::assertNull($building->sub);
        self::assertNull($building->landUse);
        self::assertNull($building->validFrom);
        self::assertSame([], $building->bpej);
    }
}
