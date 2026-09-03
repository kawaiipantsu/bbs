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
                $st['reply_to'] = (int) $m['id'];
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
        $choices = [];
        foreach ($boards as $i => $b) {
            $choices[] = [
                'key'   => (string) ($i + 1),
                'label' => sprintf('%-24s  %s msgs', mb_substr($b['name'], 0, 24), (string) $b['msgs']),
                'desc'  => (string) $b['description'],
            ];
        }
        $this->picker($f, $choices);
        if (!$boards) {
            $f->pipe('|08   No boards you can read in this conference.');
        }
        return $f->footer('↑↓ move  ·  ENTER enter  ·  C change conference · Q back');
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
        $choices = [];
        foreach ($confs as $i => $c) {
            $choices[] = [
                'key'   => (string) ($i + 1),
                'label' => $c['name'] . ((int) $c['id'] === $cur ? '  (current)' : ''),
                'desc'  => (string) $c['description'],
            ];
        }
        $this->picker($f, $choices);
        return $f->footer('↑↓ move  ·  ENTER join  ·  Q back');
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
        $choices = [];
        foreach ($withUnread as $i => $r) {
            $choices[] = [
                'key'   => (string) ($i + 1),
                'label' => sprintf('%-26s  %s', mb_substr($r['name'], 0, 26), mb_substr($r['conf'], 0, 14)),
                'desc'  => $r['unread'] . ' new message' . ((int) $r['unread'] === 1 ? '' : 's'),
            ];
        }
        $this->picker($f, $choices);
        if ($uid && !$withUnread) {
            $f->pipe('|10   You are all caught up. Nothing new.');
        }
        return $f->footer('↑↓ move  ·  ENTER read  ·  Q back');
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
        $choices = [];
        foreach ($results as $i => $r) {
            $choices[] = [
                'key'   => (string) ($i + 1),
                'label' => mb_substr($r['subject'], 0, 52),
                'desc'  => sprintf(
                    '%s in %s · %s',
                    mb_substr($r['from_handle'], 0, 16),
                    mb_substr($r['board'], 0, 14),
                    date('Y-m-d', strtotime($r['created_at']))
                ),
            ];
        }
        $this->picker($f, $choices);
        if ($q !== '' && !$results) {
            $f->pipe('|08   Nothing matched. Try fewer / different words.');
        }
        return $f->footer('↑↓ move  ·  ENTER open  ·  Q back');
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

        // ---- inside a thread (whole thread shown as an indented tree) ----
        if ($view === 'thread') {
            $msgs = $this->threadMessages((int) $st['thread']);
            if ($key === "\x1B" || $key === 'Q') {
                $st['view'] = 'threads';
                return $this->threadList($e, $board, $st);
            }
            // number / letter picks which message a reply attaches under
            $idx = null;
            if (ctype_digit($key) && $key !== '0') {
                $idx = (int) $key - 1;
            } elseif (strlen($key) === 1 && ctype_alpha($key) && $key !== 'R' && $key !== 'P' && $key !== 'B' && $key !== 'N') {
                $idx = 9 + (ord($key) - 65);
            }
            if ($idx !== null && isset($msgs[$idx])) {
                $st['reply_to'] = (int) $msgs[$idx]['id'];
            }
            if ($key === 'R' && $e->can('message.post')) {
                $st['view'] = 'reply';
                $st['reply_to'] = (int) ($st['reply_to'] ?? ($msgs[array_key_last($msgs)]['id'] ?? 0));
                return $this->run($e, 'msg.read', ['cmd' => 'render'], $st);
            }
            $this->markRead($e, (int) $board['id'], (int) ($msgs[array_key_last($msgs)]['id'] ?? 0));
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
            $idx = (int) $key - 1 + (int) ($st['page'] ?? 0) * Frame::pageSize(13);
            if (isset($threads[$idx])) {
                $st['view'] = 'thread';
                $st['thread'] = (int) $threads[$idx]['thread_id'];
                unset($st['reply_to']);
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
        $choices = [];
        foreach ($slice as $i => $t) {
            $unread = (int) $t['max_id'] > $lastRead;
            $subj = mb_substr(preg_replace('/^(Re:\s*)+/i', '', $t['subject']) ?: $t['subject'], 0, 52);
            $choices[] = [
                'key'   => (string) ($i + 1),
                'label' => ($unread ? '* ' : '  ') . $subj,
                'desc'  => sprintf(
                    '%s · %d repl · %s',
                    mb_substr($t['from_handle'], 0, 15),
                    (int) $t['replies'],
                    date('m/d H:i', strtotime($t['last_at']))
                ),
            ];
        }
        $this->picker($f, $choices);
        if (!$threads) {
            $f->pipe('|08   No messages here yet. Press P to start a thread.');
        }
        $hint = $e->can('message.post')
            ? '↑↓ move  ·  ENTER read  ·  P post · ←/→ page · Q back'
            : '↑↓ move  ·  ENTER read  ·  ←/→ page · Q back';
        return $f->footer($hint);
    }

    /**
     * Render the whole thread on one scrollable page: the opening post, then
     * every reply indented under its parent so the shape of the conversation is
     * obvious at a glance.
     */
    private function renderThread(Engine $e, array $board, array &$st): Frame
    {
        $msgs = $this->threadMessages((int) $st['thread']);
        if (!$msgs) {
            $st['view'] = 'threads';
            return $this->threadList($e, $board, $st);
        }

        // index by id, then order depth-first by parent_id
        $byId = [];
        foreach ($msgs as $m) {
            $byId[(int) $m['id']] = $m;
        }
        $kids = [];
        $roots = [];
        foreach ($msgs as $m) {
            $pid = $m['parent_id'] !== null ? (int) $m['parent_id'] : 0;
            if ($pid && isset($byId[$pid])) {
                $kids[$pid][] = (int) $m['id'];
            } else {
                $roots[] = (int) $m['id'];
            }
        }
        $ordered = [];
        $walk = function (int $id, int $depth) use (&$walk, &$ordered, &$kids) {
            $ordered[] = [$id, $depth];
            foreach ($kids[$id] ?? [] as $c) {
                $walk($c, $depth + 1);
            }
        };
        foreach ($roots as $r) {
            $walk($r, 0);
        }

        $root = $byId[$roots[0]] ?? $msgs[0];
        $replyTo = (int) ($st['reply_to'] ?? 0);
        $w = Frame::width();

        $f = Frame::make('screen')->title($root['subject'])->mode('pager')
            ->header($board['name'] . ' · ' . count($msgs) . ' post' . (count($msgs) === 1 ? '' : 's'))->blank();
        $f->pipe('|08   Subject : |15' . mb_substr(preg_replace('/^(Re:\s*)+/i', '', $root['subject']) ?: $root['subject'], 0, $w - 14));
        $f->rule();

        $labels = [];
        foreach ($ordered as $n => [$id, $depth]) {
            $m = $byId[$id];
            $labels[$id] = $n < 9 ? (string) ($n + 1) : chr(65 + $n - 9);
            $pad = str_repeat('  ', min(6, $depth));
            $branch = $depth > 0 ? '|08' . $pad . '└─ ' : '|08' . $pad;
            $mark = $id === $replyTo ? '|12▸ ' : '   ';
            $f->blank();
            $f->pipe(sprintf(
                '%s%s|08[|15%s|08] |11%s |08%s%s',
                $mark,
                $branch,
                $labels[$id],
                mb_substr($m['from_handle'], 0, 20),
                date('Y-m-d H:i', strtotime($m['created_at'])),
                $depth === 0 && $m['ip_phone'] ? '  |08(' . $m['ip_phone'] . ')' : ''
            ));
            foreach ($this->wrapBody($m['body'], $w - 8 - strlen($pad)) as $l) {
                $f->pipe('|07' . $pad . '   ' . $l);
            }
        }

        $sig = $root['from_user_id'] ? Db::val('SELECT signature FROM users WHERE id = ?', [$root['from_user_id']]) : null;
        if ($sig) {
            $f->blank()->pipe('|08   ---');
            foreach (explode("\n", (string) $sig) as $l) {
                $f->pipe('|08   ' . $l);
            }
        }

        $reply = $e->can('message.post')
            ? ' · number selects a post · R reply' . ($replyTo && isset($labels[$replyTo]) ? ' to [' . $labels[$replyTo] . ']' : '')
            : '';
        return $f->footer('SPACE / ↓ scroll' . $reply . ' · Q back to list');
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
