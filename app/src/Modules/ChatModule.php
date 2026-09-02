<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Admin\AuditLog;
use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;
use Bbs\Core\Db;

/**
 * Node chat. The heavy lifting (polling, presence, sending) is done by the JS
 * chat client against /api/chat/*. This module just enters/leaves "chat mode"
 * and drops a join line.
 */
final class ChatModule extends Module
{
    public static function slugs(): array
    {
        return ['chat'];
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        $key = strtoupper((string) ($in['key'] ?? ''));
        $cmd = (string) ($in['cmd'] ?? '');

        if (!$e->can('chat.use')) {
            return $this->denied($e, 'use node chat (accounts only)');
        }

        if ($cmd === 'leave' || $key === "\x1B") {
            Db::q('DELETE FROM chat_presence WHERE session_id = ?', [$e->session->id]);
            $handle = $e->session->handle();
            Db::insert('chat_messages', [
                'channel' => 'main', 'handle' => $handle, 'body' => $handle . ' has left chat.',
                'kind' => 'system', 'created_at' => date('Y-m-d H:i:s'),
            ]);
            return $e->exitModule();
        }

        if (($st['joined'] ?? false) === false) {
            $st['joined'] = true;
            $handle = $e->session->handle();
            Db::insert('chat_messages', [
                'channel' => 'main', 'handle' => $handle, 'body' => $handle . ' has entered chat.',
                'kind' => 'system', 'created_at' => date('Y-m-d H:i:s'),
            ]);
            AuditLog::record('chat.join', 'chat', 'main', $handle . ' joined chat');
        }

        $last = (int) Db::val('SELECT COALESCE(MAX(id),0) FROM chat_messages WHERE channel = "main"');

        return Frame::make('screen')->view('chat')->title('Node Chat')->mode('chat')
            ->header('Node Chat', 'channel #main')->blank()
            ->pipe('|08   You are in the chat room. Type and press ENTER to say something.')
            ->pipe('|08   /me does an action  ·  ESC leaves  ·  messages auto-scroll')
            ->rule()
            ->meta([
                'chat'    => true,
                'channel' => 'main',
                'since'   => $last,
                'handle'  => $e->session->handle(),
                'poll_ms' => 1800,
            ])
            ->footer('Type a message · ESC leave chat');
    }
}
