<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Admin\AuditLog;
use Bbs\Auth\Rbac;
use Bbs\Bbs\Context;
use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;
use Bbs\Core\Db;
use Bbs\Core\Queue;

/**
 * The message base: conferences -> boards -> threads -> messages, plus scan,
 * find, post and change-conference. "Current conference / board" is remembered
 * on the session so it survives moving between menu items.
 */
final class MessagesModule extends Module
{
    public static function slugs(): array
    {
        return ['msg.boards', 'msg.read', 'msg.post', 'msg.scan', 'msg.find', 'msg.conf'];
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        // deep-link args passed via goModule()
        if (isset($st['board_slug']) && $st['board_slug'] !== '') {
            $bid = Db::val('SELECT id FROM boards WHERE slug = ?', [$st['board_slug']]);
            if ($bid) {
                $e->session->put('msg.board', (int) $bid);
            }
            unset($st['board_slug']);
        }
        if (isset($st['message_id']) && (int) $st['message_id'] > 0) {
            $mid = (int) $st['message_id'];
            unset($st['message_id']);
            $m = Db::one('SELECT * FROM messages WHERE id = ? AND deleted_at IS NULL', [$mid]);
            if ($m) {
                $e->session->put('msg.board', (int) $m['board_id']);
                $st['view'] = 'thread';
                $st['thread'] = (int) $m['thread_id'];
                $st['pos'] = 0;
            }
        }

        $key = strtoupper((string) ($in['key'] ?? ''));
        $cmd = (string) ($in['cmd'] ?? '');

        return match ($slug) {
            'msg.boards' => $this->boards($e, $key, $st),
            'msg.conf'   => $this->conferences($e, $key, $st),
            'msg.scan'   => $this->scan($e, $key, $st),
            'msg.find'   => $this->find($e, $in, $cmd, $key, $st),
            'msg.post'   => $this->post($e, $in, $cmd, $key, $st),
            'msg.read'   => $this->read($e, $in, $cmd, $key, $st),
            default      => $e->exitModule(),
        };
    }

    // -----------------------------------------------------------------
    private function curConf(Engine $e): array
    {
        $id = (int) $e->session->get('msg.conf', 0);
        $conf = $id ? Db::one('SELECT * FROM conferences WHERE id = ?', [$id]) : null;
        if (!$conf) {
            $conf = Db::one('SELECT * FROM conferences WHERE is_default = 1 ORDER BY sort LIMIT 1')
                 ?? Db::one('SELECT * FROM conferences ORDER BY sort LIMIT 1');
            if ($conf) {
                $e->session->put('msg.conf', (int) $conf['id']);
            }
        }
        return $conf ?: ['id' => 0, 'name' => 'Main', 'min_role_rank' => 0];
    }

    private function curBoard(Engine $e): ?array
    {
        $id = (int) $e->session->get('msg.board', 0);
        if (!$id) {
            return null;
        }
        return Db::one('SELECT * FROM boards WHERE id = ?', [$id]);
    }

    private function boardsFor(Engine $e, int $confId): array
    {
        $rank = $e->rank();
        return Db::all(
            'SELECT b.*, (SELECT COUNT(*) FROM messages m WHERE m.board_id=b.id AND m.deleted_at IS NULL) AS msgs
             FROM boards b WHERE b.conference_id = ? AND b.min_read_rank <= ? ORDER BY b.sort, b.id',
            [$confId, $rank]
        );
    }

    // -----------------------------------------------------------------
    private function boards(Engine $e, string $key, array &$st): Frame
    {
        $conf = $this->curConf($e);
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }
        $boards = $this->boardsFor($e, (int) $conf['id']);
        if (ctype_digit($key) && $key !== '0') {
            $idx = (int) $key - 1;
            if (isset($boards[$idx])) {
                $e->session->put('msg.board', (int) $boards[$idx]['id']);
                return $e->goModule('msg.read');
            }
        }
        $f = Frame::make('screen')->title('Message Boards')->mode('menu')
            ->header('Boards - ' . $conf['name'], count($boards) . ' areas')->blank();
        $f->pipe('|08   ' . str_pad('#', 5) . str_pad('BOARD', 26) . str_pad('MSGS', 8) . 'DESCRIPTION');
        $f->rule();
        foreach ($boards as $i => $b) {
            $f->pipe(sprintf(
                '|08   [|15%2d|08] |14%-24s |07%-7s |08%s',
                $i + 1,
                mb_substr($b['name'], 0, 24),
                (string) $b['msgs'],
                mb_substr($b['description'], 0, 74)
            ));
        }
        if (!$boards) {
            $f->pipe('|08   No boards you can read in this conference.');
        }
        return $f->footer('number to enter · C change conference · Q back');
    }

    private function conferences(Engine $e, string $key, array &$st): Frame
    {
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }
        $rank = $e->rank();
        $confs = Db::all('SELECT * FROM conferences WHERE min_role_rank <= ? ORDER BY sort, id', [$rank]);
        if (ctype_digit($key) && $key !== '0') {
            $idx = (int) $key - 1;
            if (isset($confs[$idx])) {
                $e->session->put('msg.conf', (int) $confs[$idx]['id']);
                $e->session->forget('msg.board');
                return $e->goModule('msg.boards');
            }
        }
        $cur = (int) $e->session->get('msg.conf', 0);
        $f = Frame::make('screen')->title('Conferences')->mode('menu')->header('Change Conference')->blank();
        foreach ($confs as $i => $c) {
            $mark = (int) $c['id'] === $cur ? '|10*' : '|08 ';
            $f->pipe(sprintf('%s |08[|15%d|08] |14%-22s |07%s', $mark, $i + 1, $c['name'], $c['description']));
        }
        return $f->footer('number to join · Q back');
    }

    // -----------------------------------------------------------------
    private function scan(Engine $e, string $key, array &$st): Frame
    {
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }
        $uid = $e->session->userId;
        $rank = $e->rank();
        $rows = Db::all(
            "SELECT b.id, b.name, b.conference_id, c.name AS conf,
                    (SELECT COUNT(*) FROM messages m WHERE m.board_id=b.id AND m.deleted_at IS NULL
                       AND m.id > COALESCE((SELECT last_read_id FROM message_reads r WHERE r.user_id=? AND r.board_id=b.id),0)
                    ) AS unread
             FROM boards b JOIN conferences c ON c.id=b.conference_id
             WHERE b.min_read_rank <= ? ORDER BY c.sort, b.sort",
            [$uid ?: 0, $rank]
        );
        $withUnread = array_values(array_filter($rows, static fn ($r) => (int) $r['unread'] > 0));

        if (ctype_digit($key) && $key !== '0') {
            $idx = (int) $key - 1;
            if (isset($withUnread[$idx])) {
                $e->session->put('msg.board', (int) $withUnread[$idx]['id']);
                return $e->goModule('msg.read');
            }
        }

        $f = Frame::make('screen')->title('Scan New')->mode('menu')
            ->header('New Since Last Call', array_sum(array_column($rows, 'unread')) . ' new')->blank();
        if (!$uid) {
            $f->pipe('|11   Scan tracks unread per account - log in to use it.');
        }
        foreach ($withUnread as $i => $r) {
            $f->pipe(sprintf('|08   [|15%2d|08] |14%-26s |08%-14s |12%d new', $i + 1, mb_substr($r['name'], 0, 26), mb_substr($r['conf'], 0, 14), $r['unread']));
        }
        if ($uid && !$withUnread) {
            $f->pipe('|10   You are all caught up. Nothing new.');
        }
        return $f->footer('number to read · Q back');
    }

    // -----------------------------------------------------------------
    private function find(Engine $e, array $in, string $cmd, string $key, array &$st): Frame
    {
        if (($st['step'] ?? 'form') === 'form' && $cmd !== 'submit') {
            if ($key === "\x1B" || $key === 'Q') {
                return $e->exitModule();
            }
            return Frame::make('form')->title('Find')->header('Search the Message Base')->blank()
                ->pipe('|07   Full-text search across every board you can read.')
                ->form([['name' => 'q', 'label' => 'Search for', 'type' => 'text', 'max' => 100]], 'ENTER searches · ESC cancels');
        }
        if ($cmd === 'submit') {
            $st['q'] = trim((string) ($in['data']['q'] ?? ''));
            $st['step'] = 'results';
        }
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }
        $q = (string) ($st['q'] ?? '');
        $rank = $e->rank();
        $results = $q === '' ? [] : Db::all(
            "SELECT m.id, m.subject, m.from_handle, m.created_at, b.name AS board,
                    MATCH(m.subject, m.body) AGAINST (? IN NATURAL LANGUAGE MODE) AS score
             FROM messages m JOIN boards b ON b.id = m.board_id
             WHERE m.deleted_at IS NULL AND b.min_read_rank <= ?
               AND MATCH(m.subject, m.body) AGAINST (? IN NATURAL LANGUAGE MODE)
             ORDER BY score DESC LIMIT 30",
            [$q, $rank, $q]
        );
        if (ctype_digit($key) && $key !== '0') {
            $idx = (int) $key - 1;
            if (isset($results[$idx])) {
                return $e->goModule('msg.read', ['message_id' => (int) $results[$idx]['id']]);
            }
        }
        $f = Frame::make('screen')->title('Find: ' . $q)->mode('menu')
            ->header('Search results for "' . $q . '"', count($results) . ' hits')->blank();
        foreach ($results as $i => $r) {
            $f->pipe(sprintf(
                '|08   [|15%2d|08] |07%-52s |09%-16s |08%-14s %s',
                $i + 1,
                mb_substr($r['subject'], 0, 52),
                mb_substr($r['from_handle'], 0, 16),
                mb_substr($r['board'], 0, 14),
                date('Y-m-d', strtotime($r['created_at']))
            ));
        }
        if ($q !== '' && !$results) {
            $f->pipe('|08   Nothing matched. Try fewer / different words.');
        }
        return $f->footer('number to open · Q back');
    }

    // -----------------------------------------------------------------
    private function post(Engine $e, array $in, string $cmd, string $key, array &$st): Frame
    {
        if (!$e->can('message.post')) {
            return $this->denied($e, 'post messages');
        }
        $board = $this->curBoard($e);
        if (!$board) {
            return $e->goModule('msg.boards');
        }
        if ($e->rank() < (int) $board['min_post_rank']) {
            return $this->denied($e, 'post in ' . $board['name']);
        }
        if ($cmd === 'cancel' || $key === "\x1B") {
            return $e->exitModule();
        }
        if ($cmd === 'submit') {
            $d = (array) ($in['data'] ?? []);
            $subject = trim((string) ($d['subject'] ?? ''));
            $body = trim((string) ($d['body'] ?? ''));
            if ($subject === '' || $body === '') {
                return $this->postForm($e, $board, 'Subject and body are both required.')->sound('error');
            }
            $id = $this->insertMessage($e, (int) $board['id'], 0, null, (string) ($d['to'] ?? 'All'), $subject, $body);
            AuditLog::record('message.post', 'message', $id, $subject);
            Queue::push(Queue::TUBE_DISCORD, ['event' => 'message.new', 'id' => $id, 'board' => $board['name'], 'subject' => $subject, 'handle' => $e->session->handle()]);
            Context::bustStats();
            $e->session->put('msg.board', (int) $board['id']);
            return $e->goModule('msg.read', ['message_id' => $id])->sound('beep');
        }
        return $this->postForm($e, $board);
    }

    private function postForm(Engine $e, array $board, string $err = ''): Frame
    {
        return Frame::make('form')->title('Post to ' . $board['name'])->header('New message - ' . $board['name'])->blank()
            ->pipe($err ? '|12   ' . $err : '|07   Starting a new thread in ' . $board['name'] . '.')
            ->form([
                ['name' => 'to', 'label' => 'To', 'type' => 'text', 'max' => 32, 'value' => 'All'],
                ['name' => 'subject', 'label' => 'Subject', 'type' => 'text', 'max' => 118],
                ['name' => 'body', 'label' => 'Message', 'type' => 'textarea', 'max' => 8000],
            ], 'ENTER posts · ESC cancels');
    }

    // -----------------------------------------------------------------
    private function read(Engine $e, array $in, string $cmd, string $key, array &$st): Frame
    {
        $board = $this->curBoard($e);
        if (!$board) {
            return $this->boards($e, $key, $st);
        }
        $view = $st['view'] ?? 'threads';

        // ---- reply form ----
        if ($view === 'reply') {
            if ($cmd === 'cancel' || $key === "\x1B") {
                $st['view'] = 'thread';
                return $this->renderThread($e, $board, $st);
            }
            if ($cmd === 'submit') {
                $body = trim((string) ($in['data']['body'] ?? ''));
                if ($body !== '') {
                    $root = Db::one('SELECT * FROM messages WHERE id = ?', [(int) $st['reply_to']]);
                    $id = $this->insertMessage(
                        $e,
                        (int) $board['id'],
                        (int) ($root['thread_id'] ?? 0),
                        (int) $st['reply_to'],
                        $root['from_handle'] ?? 'All',
                        'Re: ' . preg_replace('/^(Re:\s*)+/i', '', (string) ($root['subject'] ?? '')),
                        $body
                    );
                    AuditLog::record('message.reply', 'message', $id, (string) ($root['subject'] ?? ''));
                    Queue::push(Queue::TUBE_DISCORD, ['event' => 'message.new', 'id' => $id, 'board' => $board['name'], 'subject' => 'Re: ' . ($root['subject'] ?? ''), 'handle' => $e->session->handle()]);
                    Context::bustStats();
                }
                $st['view'] = 'thread';
                return $this->renderThread($e, $board, $st)->sound('beep');
            }
            return Frame::make('form')->title('Reply')->header('Reply in ' . $board['name'])->blank()
                ->form([['name' => 'body', 'label' => 'Reply', 'type' => 'textarea', 'max' => 8000]], 'ENTER posts · ESC cancels');
        }

        // ---- inside a thread ----
        if ($view === 'thread') {
            $msgs = $this->threadMessages((int) $st['thread']);
            $st['pos'] = max(0, min(count($msgs) - 1, (int) ($st['pos'] ?? 0)));
            if ($key === "\x1B" || $key === 'Q') {
                $st['view'] = 'threads';
                return $this->threadList($e, $board, $st);
            }
            if ($key === 'N' || $key === ' ' || $key === "\r" || $key === "\n") {
                if ($st['pos'] < count($msgs) - 1) {
                    $st['pos']++;
                } else {
                    $st['view'] = 'threads';
                    return $this->threadList($e, $board, $st);
                }
            } elseif ($key === 'P' || $key === 'B') {
                $st['pos'] = max(0, $st['pos'] - 1);
            } elseif ($key === 'R' && $e->can('message.post')) {
                $st['view'] = 'reply';
                $st['reply_to'] = (int) ($msgs[$st['pos']]['id'] ?? 0);
                return $this->run($e, 'msg.read', ['cmd' => 'render'], $st);
            }
            $this->markRead($e, (int) $board['id'], (int) ($msgs[$st['pos']]['id'] ?? 0));
            return $this->renderThread($e, $board, $st);
        }

        // ---- thread list ----
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }
        if ($key === 'P' && $e->can('message.post')) {
            return $e->goModule('msg.post');
        }
        if (ctype_digit($key) && $key !== '0') {
            $threads = $this->threads((int) $board['id']);
            $idx = (int) $key - 1 + (int) ($st['page'] ?? 0) * 15;
            if (isset($threads[$idx])) {
                $st['view'] = 'thread';
                $st['thread'] = (int) $threads[$idx]['thread_id'];
                $st['pos'] = 0;
                return $this->renderThread($e, $board, $st);
            }
        }
        if ($key === 'RIGHT') {
            $st['page'] = (int) ($st['page'] ?? 0) + 1;
        }
        if ($key === 'LEFT') {
            $st['page'] = max(0, (int) ($st['page'] ?? 0) - 1);
        }
        return $this->threadList($e, $board, $st);
    }

    private function threadList(Engine $e, array $board, array &$st): Frame
    {
        $st['view'] = 'threads';
        $threads = $this->threads((int) $board['id']);
        $perPage = \Bbs\Bbs\Frame::pageSize(13);
        $pages = max(1, (int) ceil(count($threads) / $perPage));
        $page = max(0, min($pages - 1, (int) ($st['page'] ?? 0)));
        $st['page'] = $page;
        $slice = array_slice($threads, $page * $perPage, $perPage);
        $lastRead = (int) Db::val('SELECT last_read_id FROM message_reads WHERE user_id = ? AND board_id = ?',
            [$e->session->userId ?: 0, $board['id']]);

        $f = Frame::make('screen')->title($board['name'])->mode('menu')
            ->header($board['name'], 'page ' . ($page + 1) . '/' . $pages)->blank();
        if ($board['description']) {
            $f->pipe('|08   ' . $board['description'])->blank();
        }
        $f->pipe('|08   ' . str_pad('#', 5) . str_pad('SUBJECT', 54) . str_pad('BY', 16) . str_pad('REPL', 6) . 'LAST');
        $f->rule();
        foreach ($slice as $i => $t) {
            $new = (int) $t['max_id'] > $lastRead ? '|12*' : '|08 ';
            $f->pipe(sprintf(
                '%s |08[|15%2d|08] |07%-52s |09%-15s |08%-5d %s',
                $new,
                $page * $perPage + $i + 1,
                mb_substr(preg_replace('/^(Re:\s*)+/i', '', $t['subject']) ?: $t['subject'], 0, 52),
                mb_substr($t['from_handle'], 0, 15),
                (int) $t['replies'],
                date('m/d H:i', strtotime($t['last_at']))
            ));
        }
        if (!$threads) {
            $f->pipe('|08   No messages here yet. Press P to start a thread.');
        }
        $hint = $e->can('message.post') ? 'number read · P post · ←/→ page · Q back' : 'number read · ←/→ page · Q back';
        return $f->footer($hint);
    }

    private function renderThread(Engine $e, array $board, array &$st): Frame
    {
        $msgs = $this->threadMessages((int) $st['thread']);
        if (!$msgs) {
            $st['view'] = 'threads';
            return $this->threadList($e, $board, $st);
        }
        $pos = max(0, min(count($msgs) - 1, (int) ($st['pos'] ?? 0)));
        $m = $msgs[$pos];
        $f = Frame::make('screen')->title($m['subject'])->mode('pager')
            ->header($board['name'] . ' - msg ' . ($pos + 1) . '/' . count($msgs))->blank();
        $f->pipe('|08   Subject : |15' . $m['subject']);
        $f->pipe('|08   From    : |11' . $m['from_handle'] . '   |08To: |07' . $m['to_handle']);
        $f->pipe('|08   Date    : |07' . date('Y-m-d H:i', strtotime($m['created_at'])) . '   |08Calling from: |07' . ($m['ip_phone'] ?: 'unlisted'));
        $f->rule();
        foreach ($this->wrapBody($m['body']) as $l) {
            $f->pipe('|07   ' . $l);
        }
        $sig = Db::val('SELECT signature FROM users WHERE id = ?', [$m['from_user_id']]);
        if ($sig) {
            $f->blank()->pipe('|08   --- ');
            foreach (explode("\n", (string) $sig) as $l) {
                $f->pipe('|08   ' . $l);
            }
        }
        $reply = $e->can('message.post') ? ' · R reply' : '';
        return $f->footer('N/SPACE next · P prev' . $reply . ' · Q back to list');
    }

    // -----------------------------------------------------------------
    private function threads(int $boardId): array
    {
        return Db::all(
            "SELECT t.id AS thread_id, t.subject, t.from_handle, t.created_at,
                    MAX(r.id) AS max_id, MAX(r.created_at) AS last_at,
                    COUNT(r.id) - 1 AS replies
             FROM messages t
             JOIN messages r ON r.thread_id = t.thread_id AND r.deleted_at IS NULL
             WHERE t.board_id = ? AND t.deleted_at IS NULL AND t.id = t.thread_id
             GROUP BY t.id, t.subject, t.from_handle, t.created_at
             ORDER BY last_at DESC",
            [$boardId]
        );
    }

    private function threadMessages(int $threadId): array
    {
        return Db::all(
            'SELECT * FROM messages WHERE thread_id = ? AND deleted_at IS NULL ORDER BY id ASC',
            [$threadId]
        );
    }

    private function insertMessage(Engine $e, int $boardId, int $threadId, ?int $parentId, string $to, string $subject, string $body): int
    {
        return Db::tx(function () use ($e, $boardId, $threadId, $parentId, $to, $subject, $body) {
            $id = Db::insert('messages', [
                'board_id'     => $boardId,
                'thread_id'    => $threadId ?: 0,
                'parent_id'    => $parentId,
                'from_user_id' => $e->session->userId,
                'from_handle'  => $e->session->handle(),
                'to_handle'    => mb_substr($to ?: 'All', 0, 32),
                'subject'      => mb_substr($subject, 0, 118),
                'body'         => $body,
                'ip'           => $e->session->ip,
                'ip_phone'     => $e->session->ipPhone,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            if (!$threadId) {
                Db::update('messages', ['thread_id' => $id], ['id' => $id]);
            }
            Db::q(
                'UPDATE boards SET post_count = post_count + 1, last_post_at = NOW() WHERE id = ?',
                [$boardId]
            );
            if ($e->session->userId) {
                Db::q('UPDATE users SET posts = posts + 1 WHERE id = ?', [$e->session->userId]);
            }
            return $id;
        });
    }

    private function markRead(Engine $e, int $boardId, int $msgId): void
    {
        if (!$e->session->userId || !$msgId) {
            return;
        }
        Db::q(
            'INSERT INTO message_reads (user_id, board_id, last_read_id)
             VALUES (?,?,?) ON DUPLICATE KEY UPDATE last_read_id = GREATEST(last_read_id, VALUES(last_read_id))',
            [$e->session->userId, $boardId, $msgId]
        );
    }

    /** @return list<string> */
    private function wrapBody(string $s, int $w = 110): array
    {
        $out = [];
        foreach (explode("\n", str_replace("\r", '', $s)) as $para) {
            if ($para === '') {
                $out[] = '';
                continue;
            }
            foreach (explode("\n", wordwrap($para, $w, "\n", true)) as $l) {
                $out[] = $l;
            }
        }
        return $out;
    }
}
