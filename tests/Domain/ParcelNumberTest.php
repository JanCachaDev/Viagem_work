<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Parcel\ParcelNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParcelNumberTest extends TestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function inputs(): iterable
    {
        yield 'kmenové' => ['123', '123'];
        yield 's pododdělením' => ['1854/42', '1854/42'];
        yield 'mezery kolem lomítka' => ['1854 / 42', '1854/42'];
        yield 'stavební' => ['st. 120', 'st. 120'];
        yield 'stavební bez tečky a mezery' => ['ST120/2', 'st. 120/2'];
        yield 'text' => ['Jičín', null];
        yield 'nula' => ['0', null];
        yield 'prázdné' => ['', null];
        yield 'nulové pododdělení' => ['12/0', null];
    }

    #[DataProvider('inputs')]
    public function testParseAndFormat(string $input, ?string $expected): void
    {
        $number = ParcelNumber::tryParse($input);

        self::assertSame($expected, $number === null ? null : (string) $number);
    }
}
