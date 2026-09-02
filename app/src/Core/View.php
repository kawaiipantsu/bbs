<?php

declare(strict_types=1);

namespace Bbs\Core;

/**
 * Bare PHP template renderer for the HTML shell and OG/social pages.
 * Templates live in app/views and receive $data keys as local variables.
 */
final class View
{
    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = []): string
    {
        $file = BBS_APP . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("view not found: $template");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
