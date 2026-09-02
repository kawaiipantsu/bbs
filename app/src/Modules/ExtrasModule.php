<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;
use Bbs\Bbs\Screen;
use Bbs\Core\Db;

/**
 * Small classic-BBS odds and ends: Bulletins, What's New, Last Callers and a
 * Fortune cookie. One module, several menu slugs.
 */
final class ExtrasModule extends Module
{
    public static function slugs(): array
    {
        return ['bulletins', 'whatsnew', 'lastcallers', 'fortune'];
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        $key = strtoupper((string) ($in['key'] ?? ''));

        return match ($slug) {
            'bulletins'   => $this->bulletins($e, $key, $st),
            'whatsnew'    => $this->whatsNew($e, $key),
            'lastcallers' => $this->lastCallers($e, $key),
            'fortune'     => $this->fortune($e, $key, $st),
            default       => $e->exitModule(),
        };
    }

    // ---------------------------------------------------------------
    private function bulletins(Engine $e, string $key, array &$st): Frame
    {
        $list = Db::all("SELECT slug, title FROM screens WHERE slug LIKE 'bulletin.%' ORDER BY slug");

        if (($st['show'] ?? '') !== '') {
            if (in_array($key, ["\x1B", 'Q', "\r", 'ENTER'], true)) {
                $st['show'] = '';
                return $this->bulletins($e, '', $st);   // back to the list, don't exit
            }
            return Screen::render(Frame::make('screen'), (string) $st['show'], $e->ctx, 'Bulletin')
                ->footer('ENTER / Q  back to bulletins');
        }
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }
        $idx = ctype_digit($key) && $key !== '0' ? (int) $key - 1 : null;
        if ($idx !== null && isset($list[$idx])) {
            $st['show'] = $list[$idx]['slug'];
            return Screen::render(Frame::make('screen'), $list[$idx]['slug'], $e->ctx, 'Bulletin')
                ->footer('ENTER / Q  back to bulletins');
        }

        $f = Frame::make('screen')->title('Bulletins')->mode('menu')->header('Bulletins')->blank();
        if (!$list) {
            $f->pipe('|08   No bulletins posted. A SysOp adds them as screens named bulletin.*');
        }
        foreach ($list as $i => $b) {
            $f->pipe(sprintf('|08 [|15%2d|08] |07%s', $i + 1, $b['title'] ?: $b['slug']));
        }
        return $f->footer('number to read · Q back');
    }

    // ---------------------------------------------------------------
    private function whatsNew(Engine $e, string $key): Frame
    {
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }
        $since = date('Y-m-d H:i:s', strtotime('-2 days'));
        $uid = $e->session->userId;

        $newMsgs = (int) Db::val(
            'SELECT COUNT(*) FROM messages WHERE deleted_at IS NULL AND created_at > ?',
            [$since]
        );
        $newFiles = (int) Db::val(
            'SELECT COUNT(*) FROM files WHERE deleted_at IS NULL AND is_approved = 1 AND created_at > ?',
            [$since]
        );
        $newUsers = (int) Db::val('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND created_at > ?', [$since]);
        $news = Db::all('SELECT category, source, title, published_at FROM news_items ORDER BY published_at DESC, id DESC LIMIT 8');

        $unread = 0;
        if ($uid) {
            $unread = (int) Db::val(
                "SELECT COUNT(*) FROM messages m JOIN boards b ON b.id = m.board_id
                 WHERE m.deleted_at IS NULL
                   AND m.id > COALESCE((SELECT last_read_id FROM message_reads r WHERE r.user_id = ? AND r.board_id = b.id), 0)",
                [$uid]
            );
        }

        $f = Frame::make('screen')->title("What's New")->mode('menu')->header("What's New", 'last 48 hours')->blank();
        $f->pipe(sprintf('|07   New messages ....: |14%d', $newMsgs));
        if ($uid) {
            $f->pipe(sprintf('|07   Unread by you ...: |12%d', $unread));
        }
        $f->pipe(sprintf('|07   New files .......: |14%d', $newFiles));
        $f->pipe(sprintf('|07   New callers .....: |14%d', $newUsers));
        $f->blank()->pipe('|09   FRESH HEADLINES')->rule();
        foreach ($news as $n) {
            $f->pipe(sprintf('|08   %-5s %-14s |07%s', $n['category'], mb_substr($n['source'], 0, 14), mb_substr($n['title'], 0, 74)));
        }
        if (!$news) {
            $f->pipe('|08   news wire is quiet');
        }
        return $f->footer('M messages · N news · Q back');
    }

    // ---------------------------------------------------------------
    private function lastCallers(Engine $e, string $key): Frame
    {
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }
        $per = Frame::pageSize(8);
        $rows = Db::all(
            'SELECT handle, ip_phone, node, connected_at, seconds FROM call_log ORDER BY id DESC LIMIT ' . $per
        );
        $f = Frame::make('screen')->title('Last Callers')->mode('menu')->header('Last Callers')->blank();
        $f->pipe('|08   ' . str_pad('WHEN', 20) . str_pad('CALLER', 18) . str_pad('DIALED FROM', 18) . str_pad('NODE', 6) . 'ONLINE');
        $f->rule();
        foreach ($rows as $r) {
            $dur = $r['seconds'] !== null ? intdiv((int) $r['seconds'], 60) . 'm' : 'now';
            $f->pipe(sprintf(
                '|08   %-20s|15%-18s|08%-18s|14%-6s|07%s',
                date('Y-m-d H:i', strtotime($r['connected_at'])),
                mb_substr($r['handle'], 0, 17),
                $r['ip_phone'],
                (string) $r['node'],
                $dur
            ));
        }
        return $f->footer('Q back');
    }

    // ---------------------------------------------------------------
    private function fortune(Engine $e, string $key, array &$st): Frame
    {
        $fortunes = [
            'NO CARRIER is just the modem saying goodnight.',
            'There are 10 kinds of people: those who get binary and those who dial in at 2400 baud.',
            'The best ANSI art loads one scanline at a time and you like it.',
            'A SysOp never sleeps. A SysOp reboots.',
            'Real hackers RTFM. Then they FTFM.',
            'It works on my machine is a valid deployment strategy if your machine is the server.',
            'The S in IoT stands for security.',
            'Backups are like flossing: everyone lies about it until the appointment.',
            'Every packet is a little letter that mostly arrives.',
            'The cloud is just someone else\'s BBS.',
            'If you can read this, the carrier held.',
            'grep your feelings.',
            'A password on a sticky note is still two-factor if the note is hidden well.',
            'The network is down. Long live the network.',
            'Optimism is a build that passes on the first try.',
            'You are in a maze of twisty little subnets, all alike.',
            'The modem screams so you do not have to.',
            'Legacy code is just code that shipped.',
            'Do not anthropomorphise the load balancer. It hates that.',
            'One does not simply telnet into Mordor. Mordor runs SSH now.',
            'The fastest way to a working regex is to post a broken one online.',
            'Turn it off and on again is a peer-reviewed methodology.',
            'ANSI art: because someone had to make the terminal beautiful.',
            'Your uptime is showing.',
            'The best time to plant a mirror was 20 years ago. The second best time is rsync.',
        ];
        $st['seen'] ??= -1;
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }
        // ENTER / any key -> new fortune
        $i = random_int(0, count($fortunes) - 1);
        if ($i === $st['seen']) {
            $i = ($i + 1) % count($fortunes);
        }
        $st['seen'] = $i;

        return Frame::make('screen')->view('game')->title('Fortune')->mode('game')
            ->header('Fortune Cookie')->blank()->blank()
            ->pipe('|11   ,--------------------------------------------------------------.')
            ->pipe('|11   |')
            ->block('|15   ' . wordwrap($fortunes[$i], 58, "\n   ", true))
            ->pipe('|11   |')
            ->pipe('|11   `--------------------------------------------------------------\'')
            ->blank()->blank()
            ->footer('ENTER for another · Q back');
    }
}
