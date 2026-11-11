<?php

declare(strict_types=1);

namespace App\Domain\Shared;

final class Text
{
    private const ACCENTS = [
        'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i', 'ň' => 'n', 'ó' => 'o',
        'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z', 'ä' => 'a',
        'ö' => 'o', 'ü' => 'u', 'ľ' => 'l', 'ĺ' => 'l', 'ŕ' => 'r', 'ô' => 'o',
    ];

    /** Klíč pro vyhledávání bez ohledu na velikost písmen a diakritiku. */
    public static function searchKey(string $value): string
    {
        $value = strtr(mb_strtolower(trim($value), 'UTF-8'), self::ACCENTS);

        return (string) preg_replace('/\s+/', ' ', $value);
    }
}
