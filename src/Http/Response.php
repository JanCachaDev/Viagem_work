<?php

declare(strict_types=1);

namespace App\Http;

final readonly class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public int $status,
        public string $body,
        public array $headers = [],
    ) {
    }

    public static function json(mixed $data, int $status = 200, int $maxAge = 0): self
    {
        return new self(
            $status,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => $maxAge > 0 ? "public, max-age={$maxAge}" : 'no-store',
            ],
        );
    }

    public static function error(int $status, string $message): self
    {
        return self::json(['error' => $message], $status);
    }

    public static function vectorTile(string $data): self
    {
        return new self(200, $data, [
            'Content-Type' => 'application/vnd.mapbox-vector-tile',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->status, $this->body, [...$this->headers, $name => $value]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
    }
}
