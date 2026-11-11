<?php

declare(strict_types=1);

namespace App\Domain\Shared;

final readonly class CodeName
{
    public function __construct(
        public int $code,
        public string $name,
    ) {
    }
}
