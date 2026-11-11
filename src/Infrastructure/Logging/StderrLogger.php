<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Application\Logger;
use Throwable;

/** JSON řádky na stderr – v Dockeru je vidí `docker compose logs php`. */
final readonly class StderrLogger implements Logger
{
    public function __construct(private string $target = 'php://stderr')
    {
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        if (($context['exception'] ?? null) instanceof Throwable) {
            $context['exception'] = self::exception($context['exception']);
        }

        $line = json_encode(
            ['time' => date(DATE_ATOM), 'level' => $level, 'message' => $message] + $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );
        file_put_contents($this->target, $line . "\n", FILE_APPEND);
    }

    /** @return array<string, mixed> */
    private static function exception(Throwable $e): array
    {
        return [
            'class' => $e::class,
            'message' => $e->getMessage(),
            'at' => $e->getFile() . ':' . $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ] + ($e->getPrevious() === null ? [] : ['previous' => self::exception($e->getPrevious())]);
    }
}
