<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Logging;

use App\Infrastructure\Logging\StderrLogger;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StderrLoggerTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'log');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testWritesOneJsonLinePerEntry(): void
    {
        $logger = new StderrLogger($this->file);
        $logger->warning('WFS ČÚZK neodpovídá', ['parcelId' => 42]);
        $logger->error('druhý');

        $lines = file($this->file, FILE_IGNORE_NEW_LINES);
        self::assertCount(2, $lines);

        $entry = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('warning', $entry['level']);
        self::assertSame('WFS ČÚZK neodpovídá', $entry['message']);
        self::assertSame(42, $entry['parcelId']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $entry['time']);
        self::assertSame('error', json_decode($lines[1], true)['level']);
    }

    public function testSerializesExceptionWithPreviousChain(): void
    {
        $cause = new RuntimeException('timeout');
        (new StderrLogger($this->file))->error('selhalo', ['exception' => new LogicException('obal', previous: $cause)]);

        $exception = json_decode(file_get_contents($this->file), true)['exception'];
        self::assertSame(LogicException::class, $exception['class']);
        self::assertSame('obal', $exception['message']);
        self::assertStringContainsString('StderrLoggerTest.php:', $exception['at']);
        self::assertArrayHasKey('trace', $exception);
        self::assertSame('timeout', $exception['previous']['message']);
    }

    public function testInvalidUtf8DoesNotDropTheEntry(): void
    {
        (new StderrLogger($this->file))->error('odpověď', ['body' => "\xC3"]);

        self::assertNotNull(json_decode(file_get_contents($this->file), true));
    }
}
