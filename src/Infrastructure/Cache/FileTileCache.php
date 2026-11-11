<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Domain\Tile\TileCache;
use App\Domain\Tile\TileCoordinate;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** Ukládá dlaždice v layoutu {z}/{x}/{y}.pbf, který nginx servíruje přímo přes try_files. */
final readonly class FileTileCache implements TileCache
{
    public function __construct(private string $directory)
    {
    }

    public function get(TileCoordinate $tile): ?string
    {
        $data = @file_get_contents($this->path($tile));

        return $data === false ? null : $data;
    }

    public function put(TileCoordinate $tile, string $data): void
    {
        AtomicFile::write($this->path($tile), $data);
    }

    public function clear(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
    }

    private function path(TileCoordinate $tile): string
    {
        return "{$this->directory}/{$tile->z}/{$tile->x}/{$tile->y}.pbf";
    }
}
