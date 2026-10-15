<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pembungkus input HTTP (GET/POST) agar controller tidak membaca
 * superglobal secara langsung. Dibuat per-request via fromGlobals(),
 * atau manual dengan array kustom (mudah untuk testing).
 */
final class Request
{
    /**
     * @param array<string, mixed> $query  Parameter URL ($_GET).
     * @param array<string, mixed> $body   Parameter form ($_POST).
     */
    public function __construct(
        private array $query = [],
        private array $body = [],
        private string $method = 'GET'
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self(
            $_GET,
            $_POST,
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'))
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /**
     * Ambil integer dari query string. '' / hilang / non-numerik → $default.
     */
    public function queryInt(string $key, ?int $default = null): ?int
    {
        if (!isset($this->query[$key]) || $this->query[$key] === '') {
            return $default;
        }
        $raw = $this->query[$key];
        if (is_array($raw) || !is_numeric($raw)) {
            return $default;
        }
        return (int) $raw;
    }

    public function queryString(string $key, string $default = ''): string
    {
        $raw = $this->query[$key] ?? $default;
        if (is_array($raw)) {
            return $default;
        }
        return trim((string) $raw);
    }

    public function postString(string $key, string $default = ''): string
    {
        $raw = $this->body[$key] ?? $default;
        if (is_array($raw)) {
            return $default;
        }
        return trim((string) $raw);
    }
}
