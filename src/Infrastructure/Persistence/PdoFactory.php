<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class PdoFactory
{
    public static function create(string $dsn, string $user, string $password): PDO
    {
        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
