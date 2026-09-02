<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Admin\AuditLog;
use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;
use Bbs\Core\Db;

/**
 * Voting booth. Shows the open poll, lets a logged-in user vote once, then
 * renders an ANSI bar-graph of the results.
 */
final class PollModule extends Module
{
    public static function slugs(): array
    {
        return ['poll'];
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        $key = strtoupper((string) ($in['key'] ?? ''));
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }

        $poll = Db::one('SELECT * FROM polls WHERE is_open = 1 ORDER BY id DESC LIMIT 1')
             ?? Db::one('SELECT * FROM polls ORDER BY id DESC LIMIT 1');

        if (!$poll) {
            return Frame::make('screen')->title('Voting Booth')->mode('menu')->header('Voting Booth')->blank()
                ->pipe('|08   No polls right now. A SysOp can open one in the SysOp Area.')
                ->footer('Q back');
        }

        $options = Db::all('SELECT * FROM poll_options WHERE poll_id = ? ORDER BY sort, id', [$poll['id']]);
        $uid = $e->session->userId;
        $hasVoted = $uid && Db::val('SELECT 1 FROM poll_votes WHERE poll_id = ? AND user_id = ?', [$poll['id'], $uid]);

        // handle a vote (number key 1-9)
        if (!$hasVoted && $uid && (int) $poll['is_open'] === 1 && ctype_digit($key) && $key !== '0') {
            $idx = (int) $key - 1;
            if (isset($options[$idx])) {
                Db::tx(function () use ($poll, $options, $idx, $uid, $e) {
                    Db::q('INSERT INTO poll_votes (poll_id, option_id, user_id, ip_phone, created_at)
                           VALUES (?,?,?,?,NOW())', [$poll['id'], $options[$idx]['id'], $uid, $e->session->ipPhone]);
                    Db::q('UPDATE poll_options SET votes = votes + 1 WHERE id = ?', [$options[$idx]['id']]);
                });
                AuditLog::record('poll.vote', 'poll', (int) $poll['id'], $options[$idx]['label']);
                $hasVoted = true;
                $options = Db::all('SELECT * FROM poll_options WHERE poll_id = ? ORDER BY sort, id', [$poll['id']]);
            }
        }

        $totalVotes = array_sum(array_map(static fn ($o) => (int) $o['votes'], $options));
        $f = Frame::make('screen')->title('Voting Booth')->mode('menu')
            ->header('Voting Booth', $totalVotes . ' votes cast')->blank()
            ->pipe('|15   ' . $poll['question'])->blank();

        foreach ($options as $i => $o) {
            $pct = $totalVotes ? round(100 * $o['votes'] / $totalVotes) : 0;
            if ($hasVoted || !$uid || (int) $poll['is_open'] === 0) {
                $bar = str_repeat('█', (int) round(48 * $pct / 100));
                $f->pipe(sprintf('|08   %2d) |07%-42s', $i + 1, mb_substr($o['label'], 0, 42)));
                $f->pipe(sprintf('       |10%-48s |14%3d%% |08(%d)', $bar, $pct, $o['votes']));
            } else {
                $f->pipe(sprintf('|08   [|15%d|08] |07%s', $i + 1, $o['label']));
            }
        }

        $f->blank();
        if (!$uid) {
            $f->pipe('|11   Log in to cast a vote.');
        } elseif ($hasVoted) {
            $f->pipe('|10   Your vote is counted. Thanks.');
        } elseif ((int) $poll['is_open'] === 0) {
            $f->pipe('|08   This poll is closed.');
        } else {
            $f->pipe('|07   Press the number of your choice.');
        }

        return $f->footer('1-9 vote · Q back');
    }
}
