<?php

declare(strict_types=1);

namespace App\Application;

final readonly class NullLogger implements Logger
{
    public function warning(string $message, array $context = []): void
    {
    }

    public function error(string $message, array $context = []): void
    {
    }
}
