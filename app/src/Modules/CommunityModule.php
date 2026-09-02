<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Admin\AuditLog;
use Bbs\Auth\Rbac;
use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;
use Bbs\Core\Db;

/**
 * One-liner wall, user list, who's-online and the SysOp roster.
 */
final class CommunityModule extends Module
{
    public static function slugs(): array
    {
        return ['oneliners', 'users.list', 'users.online', 'sysops'];
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        $key = strtoupper((string) ($in['key'] ?? ''));
        $cmd = (string) ($in['cmd'] ?? '');
        if (($key === "\x1B" || $key === 'Q') && $cmd !== 'submit') {
            return $e->exitModule();
        }

        return match ($slug) {
            'oneliners'   => $this->oneliners($e, $key, $cmd, $in, $st),
            'users.list'  => $this->userList($e, $key, $st),
            'users.online' => $this->online($e),
            'sysops'      => $this->sysops($e),
            default       => $e->exitModule(),
        };
    }

    // -----------------------------------------------------------------
    private function oneliners(Engine $e, string $key, string $cmd, array $in, array &$st): Frame
    {
        if (($st['mode'] ?? '') === 'add') {
            if ($cmd === 'cancel') {
                $st['mode'] = 'list';
                return $this->oneliners($e, '', 'render', [], $st);
            }
            if ($cmd === 'submit') {
                $body = trim((string) ($in['data']['body'] ?? ''));
                if ($body !== '' && $e->can('oneliner.post')) {
                    Db::insert('oneliners', [
                        'user_id'    => $e->session->userId,
                        'handle'     => $e->session->handle(),
                        'body'       => mb_substr($body, 0, 155),
                        'ip_phone'   => $e->session->ipPhone,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    AuditLog::record('oneliner.post', 'oneliner', '', $body);
                }
                $st['mode'] = 'list';
                return $this->oneliners($e, '', 'render', [], $st)->sound('beep');
            }
            return Frame::make('form')->title('Sign the Wall')->header('One-liners')->blank()
                ->pipe('|07   Say something. 155 characters. It goes up with your handle.')
                ->form([['name' => 'body', 'label' => '>', 'type' => 'text', 'max' => 155]], 'ENTER posts · ESC cancels');
        }

        if ($key === 'A' && $e->can('oneliner.post')) {
            $st['mode'] = 'add';
            return $this->oneliners($e, '', 'render', [], $st);
        }

        $rows = Db::all(
            'SELECT handle, body, ip_phone, created_at FROM oneliners
             WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 40'
        );
        $f = Frame::make('screen')->title('One-liners')->mode('menu')
            ->header('The Wall', count($rows) . ' scrawls')->blank();
        foreach ($rows as $r) {
            $f->pipe(sprintf(
                '|08%s |11%-16s|08: |07%s',
                date('m/d', strtotime($r['created_at'])),
                mb_substr($r['handle'], 0, 16),
                $this->clip($r['body'], 96)
            ));
        }
        if (!$rows) {
            $f->pipe('|08   The wall is blank. Be the first.');
        }
        $hint = $e->can('oneliner.post') ? 'A add a line · Q back' : 'Log in to add a line · Q back';
        return $f->footer($hint);
    }

    // -----------------------------------------------------------------
    private function userList(Engine $e, string $key, array &$st): Frame
    {
        $perPage = \Bbs\Bbs\Frame::pageSize(11);
        $total = (int) Db::val("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL");
        $pages = max(1, (int) ceil($total / $perPage));
        $st['page'] ??= 0;
        if ($key === 'N' || $key === 'RIGHT') {
            $st['page'] = min($pages - 1, $st['page'] + 1);
        }
        if ($key === 'P' || $key === 'LEFT') {
            $st['page'] = max(0, $st['page'] - 1);
        }

        $rows = Db::all(
            "SELECT u.handle, u.location, u.tagline, u.posts, u.calls, u.last_login_at,
                    (SELECT r.color FROM user_roles ur JOIN roles r ON r.id=ur.role_id
                     WHERE ur.user_id=u.id ORDER BY r.`rank` DESC LIMIT 1) AS color,
                    (SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id
                     WHERE ur.user_id=u.id ORDER BY r.`rank` DESC LIMIT 1) AS role
             FROM users u WHERE u.deleted_at IS NULL
             ORDER BY u.last_login_at IS NULL, u.last_login_at DESC, u.id
             LIMIT ? OFFSET ?",
            [$perPage, $st['page'] * $perPage]
        );

        $f = Frame::make('screen')->title('User List')->mode('menu')
            ->header('User List', 'page ' . ($st['page'] + 1) . '/' . $pages . '  ·  ' . $total . ' users')->blank();
        $f->pipe('|08   ' . str_pad('HANDLE', 18) . str_pad('ROLE', 12) . str_pad('LOCATION', 22) . str_pad('POSTS', 7) . str_pad('CALLS', 7) . 'LAST CALL');
        $f->rule();
        foreach ($rows as $r) {
            $col = '|' . str_pad((string) ((int) ($r['color'] ?? 7)), 2, '0', STR_PAD_LEFT);
            $f->pipe(sprintf(
                '   %s%-18s|08%-12s|07%-22s|07%-7s%-7s|08%s',
                $col,
                mb_substr($r['handle'], 0, 17),
                mb_substr($r['role'] ?? 'User', 0, 11),
                mb_substr($r['location'] ?: '—', 0, 21),
                (string) $r['posts'],
                (string) $r['calls'],
                $r['last_login_at'] ? date('Y-m-d H:i', strtotime($r['last_login_at'])) : 'never'
            ));
        }
        return $f->footer('N/P page · Q back');
    }

    // -----------------------------------------------------------------
    private function online(Engine $e): Frame
    {
        $rows = Db::all(
            "SELECT s.node, s.ip_phone, COALESCE(u.handle,'guest') AS handle, s.last_seen_at,
                    (SELECT c.pages FROM call_log c WHERE c.session_id=s.id ORDER BY c.id DESC LIMIT 1) AS pages
             FROM sessions s LEFT JOIN users u ON u.id = s.user_id
             WHERE s.expires_at > NOW() ORDER BY s.node"
        );
        $f = Frame::make('screen')->title("Who's Online")->mode('menu')
            ->header("Who's Online", count($rows) . ' connected')->blank();
        $f->pipe('|08   ' . str_pad('NODE', 7) . str_pad('CALLER', 20) . str_pad('DIALED FROM', 20) . str_pad('PAGES', 8) . 'IDLE');
        $f->rule();
        foreach ($rows as $r) {
            $idle = time() - strtotime($r['last_seen_at']);
            $f->pipe(sprintf(
                '   |14%-7s|15%-20s|08%-20s|07%-8s|08%s',
                (string) $r['node'],
                mb_substr($r['handle'], 0, 19),
                $r['ip_phone'],
                (string) ($r['pages'] ?? 0),
                $idle < 60 ? 'active' : intdiv($idle, 60) . 'm'
            ));
        }
        return $f->footer('Q back');
    }

    // -----------------------------------------------------------------
    private function sysops(Engine $e): Frame
    {
        $rows = Db::all(
            "SELECT DISTINCT u.handle, u.tagline, u.location, r.name AS role, r.color, r.`rank`
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             WHERE r.`rank` >= 80 AND u.deleted_at IS NULL
             ORDER BY r.`rank` DESC, u.handle"
        );
        $f = Frame::make('screen')->title('SysOps')->mode('menu')
            ->header('The Staff')->blank()
            ->pipe('|07   The people who keep the lines open and read your mail.')->blank();
        foreach ($rows as $r) {
            $col = '|' . str_pad((string) ((int) $r['color']), 2, '0', STR_PAD_LEFT);
            $f->pipe(sprintf('   %s%-18s |08%-10s |07%s', $col, $r['handle'], $r['role'], $r['tagline'] ?: ''));
            if ($r['location']) {
                $f->pipe('   |08' . str_repeat(' ', 18) . $r['location']);
            }
        }
        if (!$rows) {
            $f->pipe('|08   No SysOps on record. That is unusual.');
        }
        return $f->footer('T open a ticket to reach them · Q back');
    }

    private function clip(string $s, int $n): string
    {
        return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
    }
}
