<?php

declare(strict_types=1);

namespace App\Domain\Tile;

use InvalidArgumentException;

final readonly class TileCoordinate
{
    public function __construct(
        public int $z,
        public int $x,
        public int $y,
    ) {
        if ($z < 0 || $z > ZoomLevels::MAX) {
            throw new InvalidArgumentException("Zoom mimo rozsah {$z}");
        }
        $max = (1 << $z) - 1;
        if ($x < 0 || $x > $max || $y < 0 || $y > $max) {
            throw new InvalidArgumentException("Dlaždice mimo rozsah {$z}/{$x}/{$y}");
        }
    }

    public static function fromLonLat(float $lon, float $lat, int $z): self
    {
        $n = 1 << $z;
        $x = (int) floor(($lon + 180) / 360 * $n);
        $latRad = deg2rad($lat);
        $y = (int) floor((1 - log(tan($latRad) + 1 / cos($latRad)) / M_PI) / 2 * $n);

        return new self($z, min(max($x, 0), $n - 1), min(max($y, 0), $n - 1));
    }

    public function __toString(): string
    {
        return "{$this->z}/{$this->x}/{$this->y}";
    }
}
