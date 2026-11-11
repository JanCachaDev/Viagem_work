<?php

declare(strict_types=1);

namespace App\Domain\Parcel;

use InvalidArgumentException;

final readonly class ParcelNumber
{
    public function __construct(
        public int $stem,
        public ?int $sub = null,
        public bool $buildingPlot = false,
    ) {
        if ($stem < 1 || ($sub !== null && $sub < 1)) {
            throw new InvalidArgumentException('Neplatné parcelní číslo');
        }
    }

    /** Přijímá zápisy jako „123“, „123/4“, „st. 56“. */
    public static function tryParse(string $value): ?self
    {
        if (!preg_match('~^(?<st>st\.?\s*)?(?<stem>[1-9]\d{0,5})(?:\s*/\s*(?<sub>[1-9]\d{0,3}))?$~iu', trim($value), $m)) {
            return null;
        }

        return new self(
            (int) $m['stem'],
            isset($m['sub']) && $m['sub'] !== '' ? (int) $m['sub'] : null,
            $m['st'] !== '',
        );
    }

    public function __toString(): string
    {
        return ($this->buildingPlot ? 'st. ' : '') . $this->stem . ($this->sub !== null ? '/' . $this->sub : '');
    }
}
