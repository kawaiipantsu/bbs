<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Admin\AuditLog;
use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;
use Bbs\Core\Db;
use Bbs\Core\Queue;

/**
 * SysOp ticket / "send a comment". Users file tickets, see their own thread and
 * any staff replies.
 */
final class TicketModule extends Module
{
    public static function slugs(): array
    {
        return ['ticket'];
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        $key = strtoupper((string) ($in['key'] ?? ''));
        $cmd = (string) ($in['cmd'] ?? '');
        $mode = $st['mode'] ?? 'list';

        if ($mode === 'new') {
            if ($cmd === 'cancel' || $key === "\x1B") {
                $st['mode'] = 'list';
                return $this->list($e, $st);
            }
            if ($cmd === 'submit') {
                return $this->create($e, $in, $st);
            }
            return Frame::make('form')->title('New Ticket')->header('Page the SysOp')->blank()
                ->pipe('|07   The SysOp reads these. Be specific. You will see replies here.')
                ->form([
                    ['name' => 'subject', 'label' => 'Subject', 'type' => 'text', 'max' => 150],
                    ['name' => 'category', 'label' => 'Category (bug/account/idea/other)', 'type' => 'text', 'max' => 30],
                    ['name' => 'body', 'label' => 'Message', 'type' => 'textarea', 'max' => 4000],
                ], 'ENTER submits · ESC cancels');
        }

        if ($mode === 'view') {
            if ($key === "\x1B" || $key === 'Q') {
                $st['mode'] = 'list';
                return $this->list($e, $st);
            }
            if ($cmd === 'submit') {
                return $this->reply($e, $in, $st);
            }
            if ($key === 'R' && $e->session->userId) {
                return Frame::make('form')->title('Reply')->header('Reply to ticket')->blank()
                    ->form([['name' => 'body', 'label' => 'Reply', 'type' => 'textarea', 'max' => 4000]], 'ENTER sends · ESC cancels')
                    ->meta(['ticket' => $st['ticket']]);
            }
            return $this->view($e, (int) $st['ticket']);
        }

        // list mode
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }
        if ($key === 'N') {
            $st['mode'] = 'new';
            return $this->run($e, $slug, ['cmd' => 'render'], $st);
        }
        if (ctype_digit($key) && $key !== '0') {
            $tickets = $this->myTickets($e);
            $idx = (int) $key - 1;
            if (isset($tickets[$idx])) {
                $st['mode'] = 'view';
                $st['ticket'] = (int) $tickets[$idx]['id'];
                return $this->view($e, $st['ticket']);
            }
        }
        return $this->list($e, $st);
    }

    // -----------------------------------------------------------------
    private function list(Engine $e, array &$st): Frame
    {
        $f = Frame::make('screen')->title('SysOp Tickets')->mode('menu')->header('Your Tickets')->blank();
        if (!$e->session->userId) {
            $f->pipe('|11   Guests can still send one comment:')->blank();
            $st['mode'] = 'new';
            return $this->run($e, 'ticket', ['cmd' => 'render'], $st);
        }
        $tickets = $this->myTickets($e);
        if (!$tickets) {
            $f->pipe('|08   You have no tickets open.');
        }
        $choices = [];
        foreach ($tickets as $i => $t) {
            $colour = match ($t['status']) {
                'open' => '|14', 'pending' => '|11', 'answered' => '|10', default => '|08'
            };
            $choices[] = [
                'key'   => (string) ($i + 1),
                'label' => $colour . sprintf('%-10s', strtoupper($t['status'])) . '|07 ' . mb_substr($t['subject'], 0, 46),
                'desc'  => 'filed ' . date('Y-m-d', strtotime($t['created_at'])),
            ];
        }
        $this->picker($f, $choices);
        return $f->footer('↑↓ move  ·  ENTER open  ·  N new ticket · Q back');
    }

    private function myTickets(Engine $e): array
    {
        return Db::all(
            'SELECT * FROM sysop_tickets WHERE user_id = ? ORDER BY updated_at DESC LIMIT 20',
            [$e->session->userId]
        );
    }

    private function view(Engine $e, int $id): Frame
    {
        $t = Db::one('SELECT * FROM sysop_tickets WHERE id = ? AND user_id = ?', [$id, $e->session->userId]);
        if (!$t) {
            return $e->exitModule();
        }
        $f = Frame::make('screen')->title('Ticket #' . $id)->mode('menu')
            ->header('Ticket #' . $id . ' - ' . strtoupper($t['status']), $t['category'])->blank()
            ->pipe('|15   ' . $t['subject'])->rule()
            ->pipe('|08   ' . date('Y-m-d H:i', strtotime($t['created_at'])) . '  |11' . $t['handle']);
        foreach ($this->wrap($t['body']) as $l) {
            $f->pipe('|07   ' . $l);
        }
        $replies = Db::all('SELECT * FROM ticket_replies WHERE ticket_id = ? ORDER BY created_at', [$id]);
        foreach ($replies as $r) {
            $f->blank()->pipe(sprintf(
                '|08   %s  %s%s',
                date('Y-m-d H:i', strtotime($r['created_at'])),
                $r['is_staff'] ? '|12' : '|11',
                $r['handle'] . ($r['is_staff'] ? ' (staff)' : '')
            ));
            foreach ($this->wrap($r['body']) as $l) {
                $f->pipe(($r['is_staff'] ? '|10' : '|07') . '   ' . $l);
            }
        }
        return $f->footer('R reply · Q back to list');
    }

    private function create(Engine $e, array $in, array &$st): Frame
    {
        $d = (array) ($in['data'] ?? []);
        $subject = trim((string) ($d['subject'] ?? ''));
        $body = trim((string) ($d['body'] ?? ''));
        if ($subject === '' || $body === '') {
            $st['mode'] = 'new';
            return Frame::make('form')->title('New Ticket')->header('Page the SysOp')->blank()
                ->pipe('|12   Subject and message are both required.')
                ->form([
                    ['name' => 'subject', 'label' => 'Subject', 'type' => 'text', 'max' => 150, 'value' => $subject],
                    ['name' => 'category', 'label' => 'Category', 'type' => 'text', 'max' => 30, 'value' => (string) ($d['category'] ?? '')],
                    ['name' => 'body', 'label' => 'Message', 'type' => 'textarea', 'max' => 4000, 'value' => $body],
                ], 'ENTER submits · ESC cancels')->sound('error');
        }
        $id = Db::insert('sysop_tickets', [
            'user_id'    => $e->session->userId,
            'handle'     => $e->session->handle(),
            'subject'    => mb_substr($subject, 0, 155),
            'body'       => mb_substr($body, 0, 4000),
            'category'   => mb_substr((string) ($d['category'] ?? 'general'), 0, 30) ?: 'general',
            'status'     => 'open',
            'ip_phone'   => $e->session->ipPhone,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLog::record('ticket.new', 'ticket', $id, $subject);
        Queue::push(Queue::TUBE_DISCORD, ['event' => 'ticket.new', 'id' => $id, 'subject' => $subject, 'handle' => $e->session->handle()]);
        $st['mode'] = 'list';
        return $this->list($e, $st)->sound('beep')
            ->pipe('|10   Ticket #' . $id . ' filed. Watch this space for a reply.');
    }

    private function reply(Engine $e, array $in, array &$st): Frame
    {
        $id = (int) ($st['ticket'] ?? $in['meta']['ticket'] ?? 0);
        $body = trim((string) ($in['data']['body'] ?? ''));
        $t = Db::one('SELECT * FROM sysop_tickets WHERE id = ? AND user_id = ?', [$id, $e->session->userId]);
        if ($t && $body !== '') {
            Db::insert('ticket_replies', [
                'ticket_id'  => $id,
                'user_id'    => $e->session->userId,
                'handle'     => $e->session->handle(),
                'is_staff'   => 0,
                'body'       => mb_substr($body, 0, 4000),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            Db::update('sysop_tickets', ['status' => 'open', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);
            AuditLog::record('ticket.reply', 'ticket', $id, mb_substr($body, 0, 80));
            Queue::push(Queue::TUBE_DISCORD, ['event' => 'ticket.reply', 'id' => $id, 'handle' => $e->session->handle()]);
        }
        $st['mode'] = 'view';
        return $this->view($e, $id)->sound('beep');
    }

    /** @return list<string> */
    private function wrap(string $s, int $w = 112): array
    {
        $out = [];
        foreach (explode("\n", $s) as $para) {
            $out = array_merge($out, explode("\n", wordwrap($para, $w, "\n", true)));
        }
        return $out;
    }
}
