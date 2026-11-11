<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Tile\TileCoordinate;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TileCoordinateTest extends TestCase
{
    public function testFromLonLatForJicin(): void
    {
        self::assertSame('14/8891/5524', (string) TileCoordinate::fromLonLat(15.367, 50.446, 14));
    }

    /** @return iterable<string, array{int, int, int}> */
    public static function invalid(): iterable
    {
        yield 'zoom nad maximem' => [16, 0, 0];
        yield 'záporný zoom' => [-1, 0, 0];
        yield 'x mimo rozsah' => [2, 4, 0];
        yield 'y mimo rozsah' => [2, 0, 4];
    }

    #[DataProvider('invalid')]
    public function testRejectsOutOfRange(int $z, int $x, int $y): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TileCoordinate($z, $x, $y);
    }
}
