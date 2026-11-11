<?php

declare(strict_types=1);

namespace App\Application;

/** Klíč `exception` v kontextu smí obsahovat Throwable, logger ho rozepíše i s příčinami. */
interface Logger
{
    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void;
}
