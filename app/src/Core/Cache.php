<?php

declare(strict_types=1);

namespace Bbs\Core;

/**
 * Redis-backed key/value cache and pub/sub, with a graceful no-op fallback when
 * Redis is disabled or the extension/daemon is unavailable. The BBS runs fine
 * without it (sessions fall back to the DB, chat falls back to polling).
 */
final class Cache
{
    private static ?\Redis $redis = null;
    private static bool $tried = false;
    private static string $prefix = 'bbs:';

    public static function redis(): ?\Redis
    {
        if (self::$tried) {
            return self::$redis;
        }
        self::$tried = true;

        if (!Config::bool('redis.enabled', false) || !extension_loaded('redis')) {
            return null;
        }

        try {
            $r = new \Redis();
            $ok = $r->connect(
                (string) Config::get('redis.host', '127.0.0.1'),
                Config::int('redis.port', 6379),
                1.0
            );
            if (!$ok) {
                return null;
            }
            $db = Config::int('redis.db', 0);
            if ($db > 0) {
                $r->select($db);
            }
            self::$prefix = (string) Config::get('redis.prefix', 'bbs:');
            self::$redis  = $r;
        } catch (\Throwable) {
            self::$redis = null;
        }
        return self::$redis;
    }

    public static function available(): bool
    {
        return self::redis() instanceof \Redis;
    }

    private static function k(string $key): string
    {
        return self::$prefix . $key;
    }

    public static function get(string $key): ?string
    {
        $r = self::redis();
        if (!$r) {
            return null;
        }
        $v = $r->get(self::k($key));
        return $v === false ? null : (string) $v;
    }

    public static function set(string $key, string $value, int $ttl = 0): void
    {
        $r = self::redis();
        if (!$r) {
            return;
        }
        if ($ttl > 0) {
            $r->setex(self::k($key), $ttl, $value);
        } else {
            $r->set(self::k($key), $value);
        }
    }

    public static function del(string $key): void
    {
        self::redis()?->del(self::k($key));
    }

    public static function incr(string $key, int $ttl = 0): int
    {
        $r = self::redis();
        if (!$r) {
            return 0;
        }
        $n = (int) $r->incr(self::k($key));
        if ($n === 1 && $ttl > 0) {
            $r->expire(self::k($key), $ttl);
        }
        return $n;
    }

    /** Sorted-set helpers used for chat presence. */
    public static function zAdd(string $key, float $score, string $member): void
    {
        self::redis()?->zAdd(self::k($key), $score, $member);
    }

    /** @return list<string> */
    public static function zRangeByScore(string $key, string $min, string $max): array
    {
        $r = self::redis();
        return $r ? array_map('strval', $r->zRangeByScore(self::k($key), $min, $max)) : [];
    }

    public static function zRemRangeByScore(string $key, string $min, string $max): void
    {
        self::redis()?->zRemRangeByScore(self::k($key), $min, $max);
    }

    public static function publish(string $channel, string $message): void
    {
        self::redis()?->publish(self::$prefix . $channel, $message);
    }
}
