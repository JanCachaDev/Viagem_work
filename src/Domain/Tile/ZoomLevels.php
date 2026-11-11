<?php

declare(strict_types=1);

namespace App\Domain\Tile;

final class ZoomLevels
{
    /** Pod tímto zoomem dlaždice obsahují jen hranice okresu a KÚ. */
    public const MIN_PARCEL = 9;

    /** Od tohoto zoomu jsou v dlaždici všechny parcely v plné přesnosti. */
    public const FULL_DETAIL = 14;

    /** Nejvyšší generovaný zoom, mapa ho dál zvětšuje (overzoom). */
    public const MAX = 15;
}
