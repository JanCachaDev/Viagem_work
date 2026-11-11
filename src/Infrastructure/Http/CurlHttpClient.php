<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use RuntimeException;

final readonly class CurlHttpClient
{
    public function __construct(
        private int $timeoutSeconds = 30,
        private string $userAgent = 'viagem-parcely/1.0',
    ) {
    }

    public function get(string $url, ?int $timeoutSeconds = null): string
    {
        $handle = $this->open($url, $timeoutSeconds);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        $body = curl_exec($handle);
        $this->assertSuccess($handle, $url, $body !== false);

        return (string) $body;
    }

    public function download(string $url, string $targetPath): void
    {
        $tmp = $targetPath . '.part';
        $file = fopen($tmp, 'wb') ?: throw new RuntimeException("Nelze zapisovat do {$tmp}");
        $handle = $this->open($url, 600);
        curl_setopt($handle, CURLOPT_FILE, $file);
        $ok = curl_exec($handle);
        fclose($file);

        try {
            $this->assertSuccess($handle, $url, $ok !== false);
        } catch (RuntimeException $e) {
            @unlink($tmp);
            throw $e;
        }
        rename($tmp, $targetPath);
    }

    public function exists(string $url): bool
    {
        $handle = $this->open($url, 15);
        curl_setopt_array($handle, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true]);
        curl_exec($handle);

        return curl_getinfo($handle, CURLINFO_RESPONSE_CODE) === 200;
    }

    private function open(string $url, ?int $timeoutSeconds): \CurlHandle
    {
        $handle = curl_init($url) ?: throw new RuntimeException("curl_init selhal pro {$url}");
        curl_setopt_array($handle, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeoutSeconds ?? $this->timeoutSeconds,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        ]);

        return $handle;
    }

    private function assertSuccess(\CurlHandle $handle, string $url, bool $ok): void
    {
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        if (!$ok || $status !== 200) {
            throw new RuntimeException(sprintf('Požadavek na %s selhal (HTTP %d) %s', $url, $status, curl_error($handle)));
        }
    }
}
