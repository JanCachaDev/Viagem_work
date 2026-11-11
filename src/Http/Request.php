<?php

declare(strict_types=1);

namespace App\Http;

final readonly class Request
{
    /** @param array<string, mixed> $query */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public string $clientIp = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            is_string($path) ? rawurldecode($path) : '/',
            $_GET,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        );
    }

    public function queryString(string $name): string
    {
        $value = $this->query[$name] ?? '';

        return is_string($value) ? $value : '';
    }
}
