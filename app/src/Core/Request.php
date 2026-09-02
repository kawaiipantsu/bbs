<?php

declare(strict_types=1);

namespace Bbs\Core;

/**
 * Immutable-ish view of the current HTTP request.
 */
final class Request
{
    /** @var array<string,mixed> */
    public array $body;

    private function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        array $body,
        public readonly array $headers,
        public readonly string $rawBody,
    ) {
        $this->body = $body;
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        $path   = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
        $path   = '/' . trim($path, '/');
        if ($path === '/') {
            $path = '/';
        }

        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        $raw  = file_get_contents('php://input') ?: '';
        $body = $_POST;
        $ct   = strtolower($headers['content-type'] ?? '');
        if (str_contains($ct, 'application/json') && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return new self($method, $path, $_GET, $body, $headers, $raw);
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key, $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    public function ip(): string
    {
        // REMOTE_ADDR is already the real client IP (mod_remoteip on the vhost).
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public function scheme(): string
    {
        if (Config::bool('proxy.trust_forwarded_proto', true)) {
            $xfp = strtolower($this->header('x-forwarded-proto', '') ?? '');
            if ($xfp !== '') {
                return str_contains($xfp, 'https') ? 'https' : 'http';
            }
        }
        return (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
    }

    public function isHttps(): bool
    {
        return $this->scheme() === 'https';
    }

    public function userAgent(): string
    {
        return substr($this->header('user-agent', '') ?? '', 0, 255);
    }

    public function wantsJson(): bool
    {
        return str_contains($this->header('accept', '') ?? '', 'application/json')
            || str_starts_with($this->path, '/api/')
            || str_starts_with($this->path, '/admin/api/');
    }

    public function bearer(): ?string
    {
        $h = $this->header('authorization', '') ?? '';
        return preg_match('/^Bearer\s+(.+)$/i', $h, $m) ? trim($m[1]) : null;
    }
}
