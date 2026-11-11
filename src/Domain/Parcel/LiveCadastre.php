<?php

declare(strict_types=1);

namespace App\Domain\Parcel;

interface LiveCadastre
{
    /** @throws LiveCadastreUnavailable */
    public function fetch(int $parcelId): ?LiveParcelState;
}
