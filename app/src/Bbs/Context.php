<?php

declare(strict_types=1);

namespace Bbs\Bbs;

use Bbs\Auth\Session;
use Bbs\Core\Cache;
use Bbs\Core\Config;
use Bbs\Core\Db;

/**
 * Builds the {{token}} substitution map for screen rendering: board identity,
 * live statistics (cached 30s) and per-session facts.
 */
final class Context
{
    /** @return array<string,scalar|null> */
    public static function for(Session $session): array
    {
        $stats = self::stats();
        $user  = $session->user();

        return array_merge($stats, [
            'site_name'      => Config::setting('site_name', 'THUGS(red) BBS'),
            'site_tagline'   => Config::setting('site_tagline', ''),
            'sysop_handle'   => Config::setting('sysop_handle', 'sysop'),
            'telnet_host'    => Config::setting('telnet_host', Config::get('telnet_host', 'bbs.thugs.red')),
            'version'        => Config::setting('version', '1.0.0'),
            'baud'           => Config::setting('baud', (string) Config::get('terminal.baud', 57600)),
            'nodes_total'    => Config::setting('nodes', (string) Config::get('terminal.nodes', 8)),
            'php_version'    => PHP_VERSION,
            'host_uptime'    => self::uptime(),
            'now'            => date('D M j Y  H:i') . ' UTC',
            'node'           => $session->node,
            'ip'             => $session->ip,
            'phone'          => $session->ipPhone,
            'handle'         => $user['handle'] ?? 'guest',
            'session_time'   => '—',
            'session_pages'  => '0',
            'conference_name' => '',
        ]);
    }

    /** @return array<string,scalar|null> */
    public static function stats(): array
    {
        $cached = Cache::get('stats');
        if ($cached !== null) {
            $d = json_decode($cached, true);
            if (is_array($d)) {
                return $d;
            }
        }
        $d = [
            'users_total'     => (int) Db::val('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'),
            'calls_total'     => (int) Db::val('SELECT COUNT(*) FROM call_log'),
            'messages_total'  => (int) Db::val('SELECT COUNT(*) FROM messages WHERE deleted_at IS NULL'),
            'files_total'     => (int) Db::val('SELECT COUNT(*) FROM files WHERE deleted_at IS NULL AND is_approved = 1'),
            'oneliners_total' => (int) Db::val('SELECT COUNT(*) FROM oneliners WHERE deleted_at IS NULL'),
            'last_caller'     => (string) (Db::val(
                "SELECT handle FROM call_log ORDER BY connected_at DESC LIMIT 1"
            ) ?: '—'),
        ];
        Cache::set('stats', json_encode($d), 30);
        return $d;
    }

    public static function bustStats(): void
    {
        Cache::del('stats');
    }

    private static function uptime(): string
    {
        $f = @file_get_contents('/proc/uptime');
        if ($f === false) {
            return 'unknown';
        }
        $secs = (int) floatval(explode(' ', $f)[0]);
        $d = intdiv($secs, 86400);
        $h = intdiv($secs % 86400, 3600);
        $m = intdiv($secs % 3600, 60);
        return $d > 0 ? "{$d}d {$h}h {$m}m" : "{$h}h {$m}m";
    }
}
