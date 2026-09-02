<?php

declare(strict_types=1);

namespace Bbs\Admin;

use Bbs\Core\Config;
use Bbs\Core\Db;

/**
 * Fan-out of BBS events to Discord webhooks. Called from contrib/worker.php when
 * it drains the "discord" tube. Each webhook row has a csv of event names it
 * cares about; the config key discord_events is the global allow-list.
 */
final class DiscordHook
{
    /** @param array<string,mixed> $payload */
    public static function dispatch(string $event, array $payload): array
    {
        if (!Config::bool('discord_enabled', false)) {
            return ['skipped' => 'discord disabled'];
        }
        $globally = array_filter(array_map('trim', explode(',', Config::setting('discord_events', ''))));
        if ($globally && !in_array($event, $globally, true) && empty($payload['test'])) {
            return ['skipped' => "event $event not in global list"];
        }

        $hooks = Db::all('SELECT * FROM discord_webhooks WHERE enabled = 1');
        if (!$hooks && ($def = (string) Config::get('discord.default_webhook', '')) !== '') {
            $hooks = [['name' => 'default', 'url' => $def, 'events' => '*']];
        }

        $body = self::embed($event, $payload);
        $results = [];
        foreach ($hooks as $h) {
            $wanted = trim((string) $h['events']);
            if ($wanted !== '' && $wanted !== '*'
                && !in_array($event, array_map('trim', explode(',', $wanted)), true)
                && empty($payload['test'])) {
                continue;
            }
            $results[$h['name']] = self::post($h['url'], $body);
        }
        return $results ?: ['skipped' => 'no matching webhook'];
    }

    /** @param array<string,mixed> $p */
    private static function embed(string $event, array $p): array
    {
        $site = Config::setting('site_name', 'THUGS(red) BBS');
        $url  = rtrim((string) Config::get('canonical', 'https://bbs.thugs.red'), '/');

        $titles = [
            'user.register'  => '🆕 New caller registered',
            'ticket.new'     => '🎫 New SysOp ticket',
            'ticket.reply'   => '💬 Ticket reply',
            'message.new'    => '📨 New message posted',
            'sysop.page'     => '📟 SysOp paged',
        ];
        $desc = match ($event) {
            'user.register' => "**{$p['handle']}** just got an account (dialed in from `{$p['ip_phone']}`).",
            'ticket.new'    => "**{$p['handle']}** opened: *{$p['subject']}*\n<{$url}/>",
            'ticket.reply'  => (($p['staff'] ?? false) ? 'Staff' : ($p['handle'] ?? 'Someone')) . " replied to ticket #{$p['id']}.",
            'message.new'   => "**{$p['handle']}** posted *{$p['subject']}* in {$p['board']}\n<{$url}/m/{$p['id']}>",
            'sysop.page'    => (string) ($p['message'] ?? 'A caller is paging the SysOp.'),
            default         => json_encode($p, JSON_UNESCAPED_SLASHES),
        };

        return [
            'username'   => $site,
            'avatar_url' => $url . '/media/images/favicon-180.png',
            'embeds'     => [[
                'title'       => $titles[$event] ?? $event,
                'description' => mb_substr($desc, 0, 1800),
                'color'       => 0xe2223b,
                'footer'      => ['text' => $site . ' · node ' . ($p['node'] ?? '-')],
                'timestamp'   => date('c'),
            ]],
        ];
    }

    private static function post(string $url, array $json): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return ['http' => $code, 'err' => $err ?: null, 'body' => is_string($resp) ? mb_substr($resp, 0, 200) : null];
    }
}
