<?php

declare(strict_types=1);

namespace Bbs\Http\Controllers;

use Bbs\Auth\Rbac;
use Bbs\Auth\Session;
use Bbs\Core\Cache;
use Bbs\Core\Config;
use Bbs\Core\Crypto;
use Bbs\Core\Db;
use Bbs\Core\Request;
use Bbs\Core\Response;

/**
 * Node chat. The browser uses long-poll (/api/chat/poll) by default because it
 * survives the upstream WAF cleanly; /api/chat/stream offers SSE where it works.
 * Redis pub/sub is used opportunistically to wake the stream faster.
 */
final class ChatController
{
    private const CHANNEL = 'main';

    public function poll(Request $req): Response
    {
        $session = Session::start($req);
        if (!$this->mayChat($session)) {
            return Response::error('Chat requires an account.', 403);
        }
        $since = $req->int('since', 0);
        $this->heartbeat($session);

        $rows = Db::all(
            'SELECT id, handle, body, kind, created_at FROM chat_messages
             WHERE channel = ? AND id > ? ORDER BY id ASC LIMIT 100',
            [self::CHANNEL, $since]
        );
        return Response::json([
            'messages' => $rows,
            'last'     => $rows ? (int) end($rows)['id'] : $since,
            'present'  => $this->presence(),
            'server'   => date('H:i:s'),
        ]);
    }

    public function say(Request $req): Response
    {
        $session = Session::start($req);
        if (!$this->mayChat($session)) {
            return Response::error('Chat requires an account.', 403);
        }
        $token = $req->header('x-csrf') ?? (string) $req->input('csrf', '');
        if (!Crypto::csrfCheck($session->id, $token)) {
            return Response::error('Stale token.', 419);
        }

        $body = trim((string) $req->input('body', ''));
        if ($body === '') {
            return Response::json(['ok' => true, 'skipped' => true]);
        }
        $body = mb_substr($body, 0, 480);

        // simple flood control: 1 line / 1.2s
        $rl = 'chatrl:' . $session->id;
        if (Cache::get($rl) !== null) {
            return Response::error('Slow down.', 429);
        }
        Cache::set($rl, '1', 1);

        $kind = 'say';
        if (str_starts_with($body, '/me ')) {
            $kind = 'me';
            $body = substr($body, 4);
        }

        $handle = $session->handle();
        $id = Db::insert('chat_messages', [
            'channel'    => self::CHANNEL,
            'user_id'    => $session->userId,
            'handle'     => $handle,
            'body'       => $body,
            'kind'       => $kind,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->heartbeat($session);
        Cache::publish('chat:' . self::CHANNEL, (string) $id);

        return Response::json(['ok' => true, 'id' => $id]);
    }

    public function stream(Request $req): Response
    {
        $session = Session::start($req);
        if (!$this->mayChat($session)) {
            return Response::error('Chat requires an account.', 403);
        }

        @set_time_limit(60);
        ignore_user_abort(true);
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');

        $since = $req->int('since', (int) Db::val('SELECT COALESCE(MAX(id),0) FROM chat_messages WHERE channel = ?', [self::CHANNEL]));
        $start = time();
        echo "retry: 3000\n\n";
        @ob_flush();
        @flush();

        while (!connection_aborted() && time() - $start < 45) {
            $rows = Db::all(
                'SELECT id, handle, body, kind, created_at FROM chat_messages
                 WHERE channel = ? AND id > ? ORDER BY id ASC LIMIT 50',
                [self::CHANNEL, $since]
            );
            foreach ($rows as $r) {
                $since = (int) $r['id'];
                echo 'id: ' . $r['id'] . "\n";
                echo 'event: msg' . "\n";
                echo 'data: ' . json_encode($r, JSON_UNESCAPED_SLASHES) . "\n\n";
            }
            echo 'event: ping' . "\n";
            echo 'data: ' . json_encode(['t' => date('H:i:s'), 'present' => $this->presence()], JSON_UNESCAPED_SLASHES) . "\n\n";
            $this->heartbeat($session);
            @ob_flush();
            @flush();
            usleep(1500000);
        }
        echo "event: bye\ndata: {}\n\n";
        return Response::raw('', 'text/event-stream')->withHeader('X-Stream-End', '1');
    }

    // -----------------------------------------------------------------
    private function mayChat(Session $session): bool
    {
        return !$session->isGuest() && Rbac::can($session->user(), 'chat.use');
    }

    private function heartbeat(Session $session): void
    {
        Db::q(
            'INSERT INTO chat_presence (session_id, handle, channel, last_seen_at)
             VALUES (?,?,?,NOW())
             ON DUPLICATE KEY UPDATE handle = VALUES(handle), last_seen_at = NOW()',
            [$session->id, $session->handle(), self::CHANNEL]
        );
    }

    /** @return list<string> */
    private function presence(): array
    {
        $idle = Config::int('chat_idle_secs', 90);
        Db::q('DELETE FROM chat_presence WHERE last_seen_at < NOW() - INTERVAL ? SECOND', [$idle * 3]);
        return array_values(array_unique(array_column(
            Db::all(
                'SELECT handle FROM chat_presence WHERE channel = ? AND last_seen_at > NOW() - INTERVAL ? SECOND ORDER BY handle',
                [self::CHANNEL, $idle]
            ),
            'handle'
        )));
    }
}
