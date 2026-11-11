<?php

declare(strict_types=1);

namespace App\Domain\Parcel;

/**
 * Orientační úřední cena zemědělského pozemku = součet (výměra bonitovaného dílu × cena BPEJ za m²).
 * Jde o cenu podle cenového předpisu, ne o tržní cenu.
 */
final class BpejValuation
{
    /** @param list<BpejPart> $parts */
    public static function estimate(array $parts): ?float
    {
        $total = null;
        foreach ($parts as $part) {
            $price = $part->price();
            if ($price !== null) {
                $total = ($total ?? 0.0) + $price;
            }
        }

        return $total === null ? null : round($total, 2);
    }
}
