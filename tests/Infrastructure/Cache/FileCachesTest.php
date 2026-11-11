<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Cache;

use App\Domain\Tile\TileCoordinate;
use App\Infrastructure\Cache\FileCache;
use App\Infrastructure\Cache\FileRateLimiter;
use App\Infrastructure\Cache\FileTileCache;
use PHPUnit\Framework\TestCase;

final class FileCachesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/parcely-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new FileTileCache($this->dir))->clear();
        @rmdir($this->dir);
    }

    public function testTileCacheUsesNginxLayoutAndClears(): void
    {
        $cache = new FileTileCache($this->dir);
        $tile = new TileCoordinate(14, 8891, 5524);

        self::assertNull($cache->get($tile));
        $cache->put($tile, 'data');
        self::assertSame('data', $cache->get($tile));
        self::assertFileExists("{$this->dir}/14/8891/5524.pbf");

        $cache->clear();
        self::assertNull($cache->get($tile));
    }

    public function testValueCacheRespectsTtl(): void
    {
        $cache = new FileCache($this->dir);
        $cache->set('fresh', ['a' => 1], 60);
        $cache->set('expired', 'x', -1);

        self::assertSame(['a' => 1], $cache->get('fresh'));
        self::assertNull($cache->get('expired'));
        self::assertNull($cache->get('missing'));
    }

    public function testRateLimiterBlocksAfterLimit(): void
    {
        $limiter = new FileRateLimiter($this->dir, 2, 60);

        self::assertTrue($limiter->allow('ip'));
        self::assertTrue($limiter->allow('ip'));
        self::assertFalse($limiter->allow('ip'));
        self::assertTrue($limiter->allow('other-ip'));
    }
}
