<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Cadastre\DatasetSummary;
use PHPUnit\Framework\TestCase;

final class DatasetSummaryTest extends TestCase
{
    public function testTileVersionChangesWithEveryImport(): void
    {
        $first = self::summary('2026-11-01T10:00:00+00:00');

        self::assertSame($first->tileVersion(), self::summary('2026-11-01T10:00:00+00:00')->tileVersion());
        self::assertNotSame($first->tileVersion(), self::summary('2026-12-01T10:00:00+00:00')->tileVersion());
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $first->tileVersion());
    }

    public function testEmptyDatabaseHasStableVersion(): void
    {
        self::assertSame('0', self::summary(null)->tileVersion());
    }

    private static function summary(?string $importedAt): DatasetSummary
    {
        return new DatasetSummary(0, 0, 0, null, $importedAt, null);
    }
}
