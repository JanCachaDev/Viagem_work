<?php

declare(strict_types=1);

namespace App\Application\Parcel;

use App\Domain\Parcel\LiveParcelState;
use App\Domain\Parcel\Parcel;

final readonly class LiveComparison
{
    /** @param list<string> $differences */
    private function __construct(
        public ?LiveParcelState $live,
        public bool $existsLive,
        public array $differences,
    ) {
    }

    public static function of(Parcel $parcel, ?LiveParcelState $live): self
    {
        if ($live === null) {
            return new self(null, false, ['parcela v aktuálním katastru neexistuje (zanikla nebo byla přečíslována)']);
        }

        $differences = [];
        if ($live->area !== $parcel->area) {
            $differences[] = "výměra: {$parcel->area} m² → {$live->area} m²";
        }
        if (self::normalizeLabel($live->label) !== self::normalizeLabel((string) $parcel->number)) {
            $differences[] = "označení: {$parcel->number} → {$live->label}";
        }

        return new self($live, true, $differences);
    }

    private static function normalizeLabel(string $label): string
    {
        return (string) preg_replace('~[^0-9/]~', '', $label);
    }
}
