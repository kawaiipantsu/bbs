<?php

declare(strict_types=1);

namespace Bbs\Core;

/**
 * Static configuration store.
 *
 * Base values come from app/config.php. Once the database is available, values
 * from the `settings` table are layered on top via mergeSettings() so the SysOp
 * "Global Config" screen can override defaults at runtime.
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $data = [];

    /** @var array<string,scalar|null> */
    private static array $settings = [];

    private static bool $settingsLoaded = false;

    /** @param array<string,mixed> $data */
    public static function load(array $data): void
    {
        self::$data = $data;
    }

    /**
     * Dot-path getter. Checks DB settings first for top-level keys, then the
     * file config. Example: Config::get('db.host'), Config::get('terminal.cols').
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$settings)) {
            return self::$settings[$key];
        }

        $node = self::$data;
        foreach (explode('.', $key) as $seg) {
            if (is_array($node) && array_key_exists($seg, $node)) {
                $node = $node[$seg];
            } else {
                return $default;
            }
        }
        return $node;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key, $default);
        if (is_bool($v)) {
            return $v;
        }
        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key, $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $v = self::get($key, $default);
        return is_numeric($v) ? (float) $v : $default;
    }

    /**
     * "Font scale" (0.1 .. 3.0) is applied by shrinking the character grid: a
     * bigger scale means fewer columns / rows, hence bigger glyphs on the same
     * glass. Everything that lays out to the grid width reads these, so bars,
     * page sizes and server-side wrapping all stay consistent.
     */
    public static function fontScale(): float
    {
        $v = (float) self::setting('font_scale', '1');
        if ($v <= 0.0) {
            $v = 1.0;
        }
        return max(0.1, min(3.0, $v));
    }

    public static function termCols(): int
    {
        $base = self::int('term_cols', self::int('terminal.cols', 104));
        return max(40, (int) round($base / self::fontScale()));
    }

    public static function termRows(): int
    {
        $base = self::int('term_rows', self::int('terminal.rows', 38));
        return max(16, (int) round($base / self::fontScale()));
    }

    /** Load overrides from the DB `settings` table (key => value). Safe to call once DB is up. */
    public static function loadSettings(): void
    {
        if (self::$settingsLoaded) {
            return;
        }
        try {
            $rows = Db::all('SELECT `key`, `value` FROM settings');
            foreach ($rows as $row) {
                self::$settings[$row['key']] = $row['value'];
            }
            self::$settingsLoaded = true;
        } catch (\Throwable) {
            // settings table not migrated yet - ignore, use file defaults
        }
    }

    /** @return array<string,scalar|null> */
    public static function allSettings(): array
    {
        self::loadSettings();
        return self::$settings;
    }

    public static function setting(string $key, string $default = ''): string
    {
        self::loadSettings();
        return isset(self::$settings[$key]) ? (string) self::$settings[$key] : $default;
    }
}
