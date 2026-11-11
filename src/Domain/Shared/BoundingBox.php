<?php

declare(strict_types=1);

namespace App\Domain\Shared;

final readonly class BoundingBox
{
    public function __construct(
        public float $west,
        public float $south,
        public float $east,
        public float $north,
    ) {
    }

    /** @return array{float, float, float, float} */
    public function toArray(): array
    {
        return [$this->west, $this->south, $this->east, $this->north];
    }
}
