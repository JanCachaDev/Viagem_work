<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

final readonly class FileCache
{
    public function __construct(private string $directory)
    {
    }

    public function get(string $key): mixed
    {
        $content = @file_get_contents($this->path($key));
        if ($content === false) {
            return null;
        }
        $entry = json_decode($content, true);
        if (!is_array($entry) || ($entry['expires'] ?? 0) < time()) {
            return null;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        AtomicFile::write($this->path($key), (string) json_encode([
            'expires' => time() + $ttlSeconds,
            'value' => $value,
        ]));
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . sha1($key) . '.json';
    }
}
