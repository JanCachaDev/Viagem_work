<?php

declare(strict_types=1);

namespace App\Application\Parcel;

use App\Domain\Parcel\ParcelNumber;
use App\Domain\Shared\Text;

/** Rozdělí dotaz typu „Jičín st. 123/4“ na název katastrálního území a parcelní číslo. */
final readonly class SearchQuery
{
    private function __construct(
        public ?string $areaName,
        public ?ParcelNumber $number,
    ) {
    }

    public static function parse(string $query): self
    {
        $query = trim((string) preg_replace('/\s+/u', ' ', $query));

        if (preg_match('~^(?<area>.*?)\s*(?<number>(?:st\.?\s*)?\d+(?:\s*/\s*\d+)?)$~iu', $query, $m)) {
            $number = ParcelNumber::tryParse($m['number']);
            if ($number !== null) {
                return new self(self::nullIfEmpty($m['area']), $number);
            }
        }

        return new self(self::nullIfEmpty($query), null);
    }

    public function areaSearchKey(): ?string
    {
        return $this->areaName === null ? null : Text::searchKey($this->areaName);
    }

    public function isEmpty(): bool
    {
        return $this->areaName === null && $this->number === null;
    }

    private static function nullIfEmpty(string $value): ?string
    {
        $value = trim($value, " \t,");

        return $value === '' ? null : $value;
    }
}
