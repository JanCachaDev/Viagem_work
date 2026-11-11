<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Http\RateLimiter;

/** Fixed-window limiter nad soubory se zámkem – stačí pro jeden server bez Redisu/APCu. */
final readonly class FileRateLimiter implements RateLimiter
{
    public function __construct(
        private string $directory,
        private int $limit,
        private int $windowSeconds,
    ) {
    }

    public function allow(string $key): bool
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
        $window = intdiv(time(), $this->windowSeconds);
        $handle = fopen($this->directory . '/' . sha1($key) . '.cnt', 'c+');
        if ($handle === false) {
            return true;
        }

        try {
            flock($handle, LOCK_EX);
            [$storedWindow, $count] = array_map('intval', explode(':', (string) stream_get_contents($handle)) + [0, 0]);
            $count = $storedWindow === $window ? $count + 1 : 1;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, "{$window}:{$count}");

            return $count <= $this->limit;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
