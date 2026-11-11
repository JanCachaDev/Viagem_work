<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Parcel\BpejPart;
use App\Domain\Parcel\BpejValuation;
use PHPUnit\Framework\TestCase;

final class BpejValuationTest extends TestCase
{
    public function testSumsAreaTimesPrice(): void
    {
        $price = BpejValuation::estimate([
            new BpejPart('34200', 4915, 14.04),
            new BpejPart('35700', 100, 12.95),
        ]);

        self::assertSame(70301.6, $price);
    }

    public function testPartsWithoutPriceAreIgnored(): void
    {
        self::assertSame(1000.0, BpejValuation::estimate([new BpejPart('00100', 100, 10.0), new BpejPart('99999', 50, null)]));
    }

    public function testNoPriceAvailable(): void
    {
        self::assertNull(BpejValuation::estimate([]));
        self::assertNull(BpejValuation::estimate([new BpejPart('99999', 50, null)]));
    }
}
