<?php

declare(strict_types=1);

namespace App\Http;

interface RateLimiter
{
    public function allow(string $key): bool;
}
