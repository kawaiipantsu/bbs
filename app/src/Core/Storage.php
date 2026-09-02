<?php

declare(strict_types=1);

namespace Bbs\Core;

/**
 * Filesystem access confined to ./storage (outside the web root). Meant to sit
 * on its own mount (noexec,nodev). Never serve these paths directly - stream
 * them through PHP with an auth + audit check (see ApiController::download).
 */
final class Storage
{
    private static function base(): string
    {
        return defined('BBS_STORAGE_RESOLVED') ? BBS_STORAGE_RESOLVED : (defined('BBS_STORAGE') ? BBS_STORAGE : sys_get_temp_dir());
    }

    /** Resolve a relative path inside a sub-area, rejecting traversal. */
    public static function path(string $area, string $relative = ''): string
    {
        $area = preg_replace('#[^a-z0-9_-]#i', '', $area) ?: 'files';
        $relative = str_replace(['..', "\0"], '', $relative);
        $relative = ltrim($relative, '/');
        $dir = self::base() . '/' . $area;
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        return $relative === '' ? $dir : $dir . '/' . $relative;
    }

    public static function filesPath(string $relative = ''): string
    {
        return self::path('files', $relative);
    }

    public static function cachePath(string $relative = ''): string
    {
        return self::path('cache', $relative);
    }

    public static function tmpPath(string $relative = ''): string
    {
        return self::path('tmp', $relative);
    }

    public static function put(string $area, string $relative, string $contents): bool
    {
        $target = self::path($area, $relative);
        @mkdir(dirname($target), 0770, true);
        return file_put_contents($target, $contents, LOCK_EX) !== false;
    }

    public static function get(string $area, string $relative): ?string
    {
        $target = self::path($area, $relative);
        if (!is_file($target)) {
            return null;
        }
        $data = file_get_contents($target);
        return $data === false ? null : $data;
    }

    public static function delete(string $area, string $relative): void
    {
        $target = self::path($area, $relative);
        if (is_file($target)) {
            @unlink($target);
        }
    }
}
