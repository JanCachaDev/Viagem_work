<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\Parcel\LiveComparison;
use App\Domain\Parcel\LiveParcelState;
use App\Domain\Parcel\Parcel;
use App\Domain\Parcel\ParcelNumber;
use App\Domain\Shared\BoundingBox;
use App\Domain\Shared\CodeName;
use PHPUnit\Framework\TestCase;

final class LiveComparisonTest extends TestCase
{
    public function testMatchingParcelIsUpToDate(): void
    {
        $comparison = LiveComparison::of($this->parcel(), new LiveParcelState('st. 120/3', 1537, '2025-01-10'));

        self::assertTrue($comparison->existsLive);
        self::assertSame([], $comparison->differences);
    }

    public function testReportsChangedAreaAndLabel(): void
    {
        $comparison = LiveComparison::of($this->parcel(), new LiveParcelState('121', 1600, null));

        self::assertCount(2, $comparison->differences);
        self::assertStringContainsString('1537 m² → 1600 m²', $comparison->differences[0]);
    }

    public function testMissingParcel(): void
    {
        $comparison = LiveComparison::of($this->parcel(), null);

        self::assertFalse($comparison->existsLive);
        self::assertCount(1, $comparison->differences);
    }

    private function parcel(): Parcel
    {
        return new Parcel(
            id: 1,
            number: new ParcelNumber(120, 3, true),
            cadastralArea: new CodeName(659541, 'Jičín'),
            municipality: new CodeName(572659, 'Jičín'),
            area: 1537,
            landType: new CodeName(13, 'zastavěná plocha a nádvoří'),
            landUse: null,
            protections: [],
            bpej: [],
            validFrom: null,
            bbox: new BoundingBox(0, 0, 1, 1),
            geoJson: '{}',
        );
    }
}
