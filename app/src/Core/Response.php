<?php

declare(strict_types=1);

namespace Bbs\Core;

final class Response
{
    /** @var array<string,string> */
    private array $headers = [];

    private function __construct(
        private string $body,
        private int $status = 200,
        string $contentType = 'text/html; charset=utf-8',
    ) {
        $this->headers['Content-Type'] = $contentType;
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, 'text/html; charset=utf-8');
    }

    public static function text(string $text, int $status = 200): self
    {
        return new self($text, $status, 'text/plain; charset=utf-8');
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $r = new self(
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            $status,
            'application/json; charset=utf-8'
        );
        return $r->noCache();
    }

    public static function raw(string $body, string $contentType, int $status = 200): self
    {
        return new self($body, $status, $contentType);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        $r = new self('', $status);
        $r->headers['Location'] = $to;
        return $r;
    }

    public static function error(string $message, int $status = 400): self
    {
        return self::json(['error' => $message], $status);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function noCache(): self
    {
        $this->headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
        $this->headers['Pragma']        = 'no-cache';
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $k => $v) {
                header("$k: $v");
            }
        }
        echo $this->body;
    }
}
