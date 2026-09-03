<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Admin\AuditLog;
use Bbs\Auth\Password;
use Bbs\Auth\Rbac;
use Bbs\Bbs\Context;
use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;
use Bbs\Core\Config;
use Bbs\Core\Db;
use Bbs\Core\Queue;

/**
 * The SysOp / Admin area. One module, many sub-slugs (admin.users, admin.config,
 * ...). Every write is permission-checked and written to the audit log.
 */
final class AdminModule extends Module
{
    /** slug => required permission */
    private const PERM = [
        'admin.users'    => 'admin.users',
        'admin.messages' => 'admin.content',
        'admin.files'    => 'admin.content',
        'admin.news'     => 'admin.content',
        'admin.polls'    => 'admin.content',
        'admin.screens'  => 'admin.screens',
        'admin.config'   => 'admin.config',
        'admin.discord'  => 'admin.integrations',
        'admin.tickets'  => 'ticket.manage',
        'admin.audit'    => 'admin.audit',
        'admin.calls'    => 'admin.calls',
        'admin.maint'    => 'admin.config',
    ];

    public static function slugs(): array
    {
        return array_keys(self::PERM);
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        $perm = self::PERM[$slug] ?? 'admin.access';
        if (!$e->can($perm)) {
            return $this->denied($e, 'use ' . $slug);
        }

        $key = strtoupper((string) ($in['key'] ?? ''));
        $cmd = (string) ($in['cmd'] ?? '');
        if (($key === "\x1B" || $key === 'Q') && ($st['view'] ?? '') === '' && $cmd !== 'submit') {
            return $e->exitModule();
        }

        return match ($slug) {
            'admin.users'    => $this->users($e, $in, $key, $cmd, $st),
            'admin.config'   => $this->config($e, $in, $key, $cmd, $st),
            'admin.screens'  => $this->screens($e, $in, $key, $cmd, $st),
            'admin.news'     => $this->news($e, $in, $key, $cmd, $st),
            'admin.discord'  => $this->discord($e, $in, $key, $cmd, $st),
            'admin.tickets'  => $this->tickets($e, $in, $key, $cmd, $st),
            'admin.audit'    => $this->audit($e, $in, $key, $st),
            'admin.calls'    => $this->calls($e, $in, $key, $st),
            'admin.messages' => $this->messages($e, $in, $key, $cmd, $st),
            'admin.files'    => $this->files($e, $in, $key, $cmd, $st),
            'admin.polls'    => $this->polls($e, $in, $key, $cmd, $st),
            'admin.maint'    => $this->maint($e, $in, $key, $cmd, $st),
            default          => $e->exitModule(),
        };
    }

    // ===============================================================
    //  MAINTENANCE MODE
    // ===============================================================
    private function maint(Engine $e, array $in, string $key, string $cmd, array &$st): Frame
    {
        if ($cmd === 'submit' && ($st['view'] ?? '') === 'msg') {
            Db::q('UPDATE settings SET `value` = ? WHERE `key` = ?', [(string) ($in['data']['msg'] ?? ''), 'maintenance_msg']);
            AuditLog::record('admin.maint.msg', 'setting', 'maintenance_msg', 'edited');
            Context::bustStats();
            $st['view'] = '';
            return $this->maint($e, ['cmd' => 'render'], '', '', $st)->sound('beep');
        }
        if ($f = $this->back($e, $key, $st)) {
            return $f;
        }
        $on = Config::bool('maintenance', false);
        if ($key === 'T') {
            $on = !$on;
            Db::q('UPDATE settings SET `value` = ? WHERE `key` = ?', [$on ? '1' : '0', 'maintenance']);
            AuditLog::record('admin.maint.toggle', 'setting', 'maintenance', $on ? 'ENABLED' : 'disabled');
            Context::bustStats();
        }
        if ($key === 'M') {
            $st['view'] = 'msg';
            return Frame::make('form')->title('Maintenance Message')->header('Maintenance · message')->blank()
                ->form([['name' => 'msg', 'label' => 'Message', 'type' => 'textarea', 'max' => 600,
                         'value' => Config::setting('maintenance_msg', '')]], 'ENTER saves · ESC cancels');
        }

        $active = (int) Db::val("SELECT COUNT(DISTINCT node) FROM sessions WHERE expires_at > NOW() AND node > 0");
        $f = Frame::make('screen')->title('Maintenance Mode')->mode('menu')
            ->header('SysOp · Maintenance Mode')->blank();
        $f->pipe($on
            ? '|12   ●  MAINTENANCE MODE IS ON  -  callers hear a busy tone.'
            : '|10   ○  Maintenance mode is off - the board is open.');
        $f->blank();
        $f->pipe('|07   When on, anyone who is not staff (rank < 80) gets the dial-in')
          ->pipe('|07   sequence ending in an engaged/busy signal that loops. Staff still')
          ->pipe('|07   connect normally so you can work.')
          ->blank()
          ->pipe('|08   Current callers online: |14' . $active)
          ->blank()
          ->pipe('|08   Message shown:')
          ->block('|07   "' . wordwrap(Config::setting('maintenance_msg', ''), 74, "\n   ", true) . '"')
          ->blank();
        return $f->footer('T toggle ' . ($on ? 'OFF' : 'ON') . ' · M edit message · Q back');
    }

    private function back(Engine $e, string $key, array &$st): ?Frame
    {
        if ($key === "\x1B" || $key === 'Q') {
            if (($st['view'] ?? '') !== '') {
                $st['view'] = '';
                return null;
            }
            return $e->exitModule();
        }
        return null;
    }

    // ===============================================================
    //  USERS & ROLES
    // ===============================================================
    private function users(Engine $e, array $in, string $key, string $cmd, array &$st): Frame
    {
        $view = $st['view'] ?? '';

        if ($view === 'detail') {
            return $this->userDetail($e, $in, $key, $cmd, $st);
        }
        if ($view === 'search' && $cmd === 'submit') {
            $st['q'] = trim((string) ($in['data']['q'] ?? ''));
            $st['view'] = '';
        } elseif ($key === 'S') {
            $st['view'] = 'search';
            return Frame::make('form')->title('Find User')->header('Find User')->blank()
                ->form([['name' => 'q', 'label' => 'Handle contains', 'type' => 'text', 'max' => 32]], 'ENTER · ESC');
        }
        if ($f = $this->back($e, $key, $st)) {
            return $f;
        }

        $q = (string) ($st['q'] ?? '');
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $rows = Db::all(
            "SELECT u.*, (SELECT GROUP_CONCAT(r.slug) FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id) AS roles
             FROM users u WHERE u.deleted_at IS NULL AND (? = '' OR u.handle LIKE ?)
             ORDER BY u.id LIMIT " . Frame::pageSize(8),
            [$q, $like]
        );
        if (ctype_digit($key) && $key !== '0') {
            $idx = (int) $key - 1;
            if (isset($rows[$idx])) {
                $st['view'] = 'detail';
                $st['uid'] = (int) $rows[$idx]['id'];
                return $this->userDetail($e, ['cmd' => 'render'], '', '', $st);
            }
        }

        $f = Frame::make('screen')->title('Users & Roles')->mode('menu')
            ->header('SysOp · Users', count($rows) . ($q ? ' matching "' . $q . '"' : ' shown'))->blank();
        $choices = [];
        foreach ($rows as $i => $r) {
            $sc = match ($r['status']) { 'banned' => '|12', 'suspended' => '|11', 'pending' => '|13', default => '|10' };
            $choices[] = [
                'key'   => (string) ($i + 1),
                'label' => '|15' . mb_substr($r['handle'], 0, 18) . '  ' . $sc . $r['status'],
                'desc'  => trim((string) $r['roles'] === '' ? 'no roles' : (string) $r['roles'])
                    . ' · last call ' . ($r['last_login_at'] ? date('Y-m-d H:i', strtotime($r['last_login_at'])) : 'never'),
            ];
        }
        $this->picker($f, $choices);
        return $f->footer('↑↓ move  ·  ENTER edit  ·  S search · Q back');
    }

    private function userDetail(Engine $e, array $in, string $key, string $cmd, array &$st): Frame
    {
        $uid = (int) ($st['uid'] ?? 0);
        $u = Db::one('SELECT * FROM users WHERE id = ?', [$uid]);
        if (!$u) {
            $st['view'] = '';
            return $this->users($e, [], '', '', $st);
        }

        if ($cmd === 'submit' && ($st['action'] ?? '') === 'resetpw') {
            $np = (string) ($in['data']['password'] ?? '');
            if (!Password::validationError($np)) {
                Db::update('users', ['password_hash' => Password::hash($np), 'must_change_password' => 1], ['id' => $uid]);
                AuditLog::record('admin.user.resetpw', 'user', $uid, 'password reset for ' . $u['handle']);
            }
            $st['action'] = '';
            return $this->userDetail($e, ['cmd' => 'render'], '', '', $st)->sound('beep');
        }

        if ($key !== '' && $key !== 'ENTER') {
            $roles = Db::all('SELECT slug FROM roles ORDER BY `rank`');
            switch ($key) {
                case 'B':
                    $new = $u['status'] === 'banned' ? 'active' : 'banned';
                    Db::update('users', ['status' => $new], ['id' => $uid]);
                    AuditLog::record('admin.user.status', 'user', $uid, "$u[handle] -> $new");
                    break;
                case 'U':
                    $new = $u['status'] === 'suspended' ? 'active' : 'suspended';
                    Db::update('users', ['status' => $new], ['id' => $uid]);
                    AuditLog::record('admin.user.status', 'user', $uid, "$u[handle] -> $new");
                    break;
                case 'P':
                    $st['action'] = 'resetpw';
                    return Frame::make('form')->title('Reset Password')->header('Reset password for ' . $u['handle'])->blank()
                        ->form([['name' => 'password', 'label' => 'New password', 'type' => 'text', 'max' => 200]], 'ENTER · ESC');
                case 'I':
                    if ($e->can('admin.impersonate') && $uid !== $e->session->userId) {
                        AuditLog::record('admin.impersonate', 'user', $uid, 'impersonating ' . $u['handle']);
                        $e->session->login($uid);
                        $e->replaceStack([['t' => 'menu', 'ref' => 'main']]);
                        return $e->renderCurrentFrame()->sound('connect')->pipe('|12   Now acting as ' . $u['handle']);
                    }
                    break;
                case "\x1B": case 'Q':
                    $st['view'] = '';
                    return $this->users($e, [], '', '', $st);
                default:
                    // 1..9 => toggle role by index
                    if (ctype_digit($key)) {
                        $idx = (int) $key - 1;
                        if (isset($roles[$idx])) {
                            $slug = $roles[$idx]['slug'];
                            $has = Db::val('SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.slug=?', [$uid, $slug]);
                            if ($has) {
                                Rbac::revoke($uid, $slug);
                                AuditLog::record('admin.role.revoke', 'user', $uid, "$slug from $u[handle]");
                            } else {
                                Rbac::grant($uid, $slug, $e->session->userId);
                                AuditLog::record('admin.role.grant', 'user', $uid, "$slug to $u[handle]");
                            }
                        }
                    }
            }
            $u = Db::one('SELECT * FROM users WHERE id = ?', [$uid]);
        }

        $roles = Db::all('SELECT * FROM roles ORDER BY `rank`');
        $userRoles = array_column(Rbac::rolesOf($uid), 'slug');
        $f = Frame::make('screen')->title('User: ' . $u['handle'])->mode('menu')
            ->header('SysOp · ' . $u['handle'], strtoupper($u['status']))->blank();
        $f->pipe('|07   ID .........: |15' . $u['id']);
        $f->pipe('|07   Created ....: |07' . $u['created_at']);
        $f->pipe('|07   Last call ..: |07' . ($u['last_login_at'] ?: 'never') . '  from ' . ($u['last_login_phone'] ?: '—') . '  (' . ($u['last_login_ip'] ?: '—') . ')');
        $f->pipe(sprintf('|07   Stats ......: |14%d|07 calls · |14%d|07 posts · |14%d|07 up · |14%d|07 dn', $u['calls'], $u['posts'], $u['uploads'], $u['downloads']));
        $f->blank()->pipe('|14   ROLES  |08(ENTER or number toggles)');
        $roleChoices = [];
        foreach ($roles as $i => $r) {
            $has = in_array($r['slug'], $userRoles, true);
            $roleChoices[] = [
                'key'   => (string) ($i + 1),
                'label' => ($has ? '|10[x] ' : '|08[ ] ') . '|07' . $r['name'],
                'desc'  => 'rank ' . $r['rank'] . ($has ? ' · granted' : ''),
            ];
        }
        $this->picker($f, $roleChoices);
        $f->blank()->pipe('|14   ACTIONS');
        $f->pipe('|08     B |07' . ($u['status'] === 'banned' ? 'un-ban' : 'ban') . '     U ' . ($u['status'] === 'suspended' ? 'un-suspend' : 'suspend') . '     P reset password' . ($e->can('admin.impersonate') ? '     I impersonate' : ''));
        return $f->footer('↑↓ move  ·  ENTER toggle role  ·  B/U/P' . ($e->can('admin.impersonate') ? '/I' : '') . ' · Q back');
    }

    // ===============================================================
    //  GLOBAL CONFIG
    // ===============================================================
    private function config(Engine $e, array $in, string $key, string $cmd, array &$st): Frame
    {
        if ($cmd === 'submit' && isset($st['editing'])) {
            $k = (string) $st['editing'];
            $v = (string) ($in['data']['value'] ?? '');
            Db::q('UPDATE settings SET `value` = ? WHERE `key` = ?', [$v, $k]);
            AuditLog::record('admin.config.set', 'setting', $k, $k . ' = ' . mb_substr($v, 0, 80));
            Context::bustStats();
            unset($st['editing']);
            return $this->config($e, ['cmd' => 'render'], '', '', $st)->sound('beep');
        }
        if ($f = $this->back($e, $key, $st)) {
            return $f;
        }

        $rows = Db::all('SELECT * FROM settings ORDER BY category, `key`');
        if (ctype_digit($key) || (strlen($key) === 2 && ctype_digit($key))) {
            $idx = (int) $key - 1;
            if (isset($rows[$idx])) {
                $st['editing'] = $rows[$idx]['key'];
                $r = $rows[$idx];
                $type = in_array($r['type'], ['text', 'json'], true) ? 'textarea' : 'text';
                return Frame::make('form')->title('Edit ' . $r['key'])->header('Config · ' . $r['key'])->blank()
                    ->pipe('|08   ' . $r['label'] . '  (' . $r['type'] . ')')
                    ->form([['name' => 'value', 'label' => $r['key'], 'type' => $type, 'max' => 8000, 'value' => $r['value']]], 'ENTER saves · ESC cancels');
            }
        }

        $f = Frame::make('screen')->title('Global Config')->mode('menu')->header('SysOp · Global Config')->blank();
        $cat = '';
        $n = 0;
        foreach ($rows as $r) {
            $n++;
            if ($r['category'] !== $cat) {
                $cat = $r['category'];
                $f->blank()->pipe('|14   [' . strtoupper($cat) . ']');
            }
            $val = $r['type'] === 'secret' && $r['value'] !== '' ? '********' : mb_substr(str_replace("\n", ' / ', $r['value']), 0, 68);
            $this->picker($f, [[
                'key'   => (string) $n,
                'label' => sprintf('%-26s |08%s', $r['key'], $val),
                'desc'  => (string) ($r['label'] ?? ''),
            ]]);
        }
        return $f->footer('↑↓ move  ·  ENTER edit  ·  Q back  ·  (changes apply immediately)');
    }

    // ===============================================================
    //  SCREENS & MENUS
    // ===============================================================
    private function screens(Engine $e, array $in, string $key, string $cmd, array &$st): Frame
    {
        if ($cmd === 'submit' && isset($st['editing'])) {
            Db::q(
                'UPDATE screens SET body = ?, content_type = ?, updated_at = NOW() WHERE slug = ?',
                [(string) ($in['data']['body'] ?? ''), (string) ($in['data']['content_type'] ?? 'pipe'), (string) $st['editing']]
            );
            AuditLog::record('admin.screen.edit', 'screen', (string) $st['editing'], 'edited');
            unset($st['editing']);
            return $this->screens($e, ['cmd' => 'render'], '', '', $st)->sound('beep');
        }
        if ($cmd === 'submit' && isset($st['edititem'])) {
            $d = (array) ($in['data'] ?? []);
            Db::update('menu_items', [
                'label'   => mb_substr((string) ($d['label'] ?? ''), 0, 80),
                'hotkey'  => mb_substr((string) ($d['hotkey'] ?? ''), 0, 8),
                'sort'    => (int) ($d['sort'] ?? 0),
                'enabled' => ($d['enabled'] ?? '1') === '0' ? 0 : 1,
            ], ['id' => (int) $st['edititem']]);
            AuditLog::record('admin.menu.edit', 'menu_item', (int) $st['edititem'], 'edited');
            unset($st['edititem']);
            $st['view'] = 'menus';
            return $this->screens($e, ['cmd' => 'render'], '', '', $st)->sound('beep');
        }
        if ($f = $this->back($e, $key, $st)) {
            return $f;
        }

        $view = $st['view'] ?? 'screens';
        if ($key === 'M') {
            $view = $st['view'] = 'menus';
        }
        if ($key === 'S') {
            $view = $st['view'] = 'screens';
        }

        if ($view === 'menus') {
            $items = Db::all('SELECT mi.*, m.slug AS menu FROM menu_items mi JOIN menus m ON m.id = mi.menu_id ORDER BY m.slug, mi.sort');
            if (ctype_digit($key) && $key !== '0') {
                $idx = (int) $key - 1;
                if (isset($items[$idx])) {
                    $it = $items[$idx];
                    $st['edititem'] = (int) $it['id'];
                    return Frame::make('form')->title('Menu item')->header('Edit: ' . $it['menu'] . ' / ' . $it['label'])->blank()
                        ->form([
                            ['name' => 'label', 'label' => 'Label', 'type' => 'text', 'max' => 80, 'value' => $it['label']],
                            ['name' => 'hotkey', 'label' => 'Hotkey', 'type' => 'text', 'max' => 8, 'value' => $it['hotkey']],
                            ['name' => 'sort', 'label' => 'Sort', 'type' => 'text', 'max' => 5, 'value' => (string) $it['sort']],
                            ['name' => 'enabled', 'label' => 'Enabled (1/0)', 'type' => 'text', 'max' => 1, 'value' => (string) $it['enabled']],
                        ], 'ENTER saves · ESC cancels');
                }
            }
            $f = Frame::make('screen')->title('Menu Editor')->mode('menu')->header('SysOp · Menu Tree')->blank();
            $cur = '';
            $n = 0;
            foreach ($items as $it) {
                $n++;
                if ($it['menu'] !== $cur) {
                    $cur = $it['menu'];
                    $f->blank()->pipe('|14   [' . strtoupper($cur) . ']');
                }
                $en = $it['enabled'] ? '|07' : '|08';
                $this->picker($f, [[
                    'key'   => (string) $n,
                    'label' => $en . sprintf('[%-3s] %-28s', $it['hotkey'], $it['label']) . ' |08' . $it['action'] . ':' . $it['target'],
                    'desc'  => ($it['enabled'] ? 'enabled' : 'disabled') . ' · sort ' . $it['sort'],
                ]]);
            }
            return $f->footer('↑↓ move  ·  ENTER edit item  ·  S screens · Q back');
        }

        // screens list
        $screens = Db::all('SELECT * FROM screens ORDER BY slug');
        if (ctype_digit($key) && $key !== '0') {
            $idx = (int) $key - 1;
            if (isset($screens[$idx])) {
                $s = $screens[$idx];
                $st['editing'] = $s['slug'];
                return Frame::make('form')->title('Edit ' . $s['slug'])->header('Screen · ' . $s['slug'])->blank()
                    ->pipe('|08   Pipe codes: |00..|15 fg, |16..|23 bg. Tokens: {{site_name}} {{phone}} etc.')
                    ->form([
                        ['name' => 'content_type', 'label' => 'Type (pipe/ansi/plain)', 'type' => 'text', 'max' => 6, 'value' => $s['content_type']],
                        ['name' => 'body', 'label' => 'Body', 'type' => 'textarea', 'max' => 20000, 'value' => $s['body'], 'rows' => 20],
                    ], 'ENTER saves · ESC cancels');
            }
        }
        $f = Frame::make('screen')->title('Screen Editor')->mode('menu')->header('SysOp · Screens')->blank();
        $choices = [];
        foreach ($screens as $i => $s) {
            $choices[] = [
                'key'   => (string) ($i + 1),
                'label' => sprintf('%-20s |08%s', $s['slug'], $s['content_type']),
                'desc'  => mb_substr((string) $s['title'], 0, 60),
            ];
        }
        $this->picker($f, $choices);
        return $f->footer('↑↓ move  ·  ENTER edit  ·  M menu editor · Q back');
    }

    // ===============================================================
    //  NEWS & FEEDS
    // ===============================================================
    private function news(Engine $e, array $in, string $key, string $cmd, array &$st): Frame
    {
        if ($key === 'R') {
            Queue::push(Queue::TUBE_NEWS, ['event' => 'news.refresh', 'all' => true]);
            AuditLog::record('admin.news.refresh', 'news', '', 'manual refresh queued');
            $ran = 0;
            if (class_exists(\Bbs\Bbs\NewsFetcher::class)) {
                $ran = \Bbs\Bbs\NewsFetcher::run();
            }
            return $this->news($e, ['cmd' => 'render'], '', '', $st)->sound('beep')
                ->pipe('|10   Refresh queued' . ($ran ? " · fetched $ran items now" : ''));
        }
        if ($f = $this->back($e, $key, $st)) {
            return $f;
        }
        $counts = [];
        foreach (['it', 'hacking', 'tech', 'entertainment'] as $c) {
            $counts[$c] = (int) Db::val('SELECT COUNT(*) FROM news_items WHERE category = ?', [$c]);
        }
        $last = Db::val('SELECT MAX(fetched_at) FROM news_items');
        $f = Frame::make('screen')->title('News & Feeds')->mode('menu')->header('SysOp · News')->blank()
            ->pipe('|07   Last fetch: |14' . ($last ?: 'never'))->blank();
        foreach ($counts as $c => $n) {
            $f->pipe(sprintf('|08   %-14s |14%4d |08cached', strtoupper($c), $n));
        }
        $f->blank()->pipe('|08   Feed URLs are edited in Global Config: news_feeds_it, news_feeds_hacking, ...');
        $recent = Db::all('SELECT category, source, title, published_at FROM news_items ORDER BY id DESC LIMIT 12');
        $f->blank()->pipe('|14   RECENT');
        foreach ($recent as $r) {
            $f->pipe(sprintf('|08   %-5s %-14s |07%s', $r['category'], mb_substr($r['source'], 0, 14), mb_substr($r['title'], 0, 82)));
        }
        return $f->footer('R refresh now · Q back');
    }

    // ===============================================================
    //  DISCORD WEBHOOKS
    // ===============================================================
    private function discord(Engine $e, array $in, string $key, string $cmd, array &$st): Frame
    {
        if ($cmd === 'submit' && ($st['view'] ?? '') === 'add') {
            $d = (array) ($in['data'] ?? []);
            $url = trim((string) ($d['url'] ?? ''));
            if (preg_match('#^https://(discord\.com|discordapp\.com)/api/webhooks/#', $url)) {
                Db::insert('discord_webhooks', [
                    'name'    => mb_substr((string) ($d['name'] ?? 'hook'), 0, 60),
                    'url'     => $url,
                    'events'  => mb_substr((string) ($d['events'] ?? 'user.register,ticket.new,message.new'), 0, 255),
                    'enabled' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                AuditLog::record('admin.discord.add', 'webhook', '', (string) ($d['name'] ?? ''));
            }
            $st['view'] = '';
            return $this->discord($e, ['cmd' => 'render'], '', '', $st)->sound('beep');
        }
        if ($f = $this->back($e, $key, $st)) {
            return $f;
        }
        $hooks = Db::all('SELECT * FROM discord_webhooks ORDER BY id');
        if ($key === 'A') {
            $st['view'] = 'add';
            return Frame::make('form')->title('Add Webhook')->header('New Discord webhook')->blank()
                ->form([
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'max' => 60],
                    ['name' => 'url', 'label' => 'Webhook URL', 'type' => 'text', 'max' => 400],
                    ['name' => 'events', 'label' => 'Events (csv)', 'type' => 'text', 'max' => 255, 'value' => 'user.register,ticket.new,ticket.reply,message.new,sysop.page'],
                ], 'ENTER saves · ESC cancels');
        }
        if (ctype_digit($key) && $key !== '0') {
            $idx = (int) $key - 1;
            if (isset($hooks[$idx])) {
                $h = $hooks[$idx];
                Db::update('discord_webhooks', ['enabled' => $h['enabled'] ? 0 : 1], ['id' => $h['id']]);
                AuditLog::record('admin.discord.toggle', 'webhook', (int) $h['id'], $h['name']);
            }
        }
        if ($key === 'T' && $hooks) {
            Queue::push(Queue::TUBE_DISCORD, ['event' => 'sysop.page', 'test' => true, 'message' => 'Test ping from ' . $e->session->handle()]);
            return $this->discord($e, ['cmd' => 'render'], '', '', $st)->pipe('|10   Test message queued.');
        }
        $f = Frame::make('screen')->title('Discord Hooks')->mode('menu')->header('SysOp · Discord')->blank();
        foreach ($hooks as $i => $h) {
            $on = $h['enabled'] ? '|10ON ' : '|08OFF';
            $this->picker($f, [[
                'key'   => (string) ($i + 1),
                'label' => $on . ' |11' . $h['name'] . '  |08' . mb_substr($h['events'], 0, 60),
                'desc'  => preg_replace('#(/webhooks/\d+/).*#', '$1********', $h['url']) ?? (string) $h['url'],
            ]]);
            $f->pipe('|08         ' . preg_replace('#(/webhooks/\d+/).*#', '$1********', $h['url']));
        }
        if (!$hooks) {
            $f->pipe('|08   No webhooks configured. Press A to add one.');
        }
        $f->blank()->pipe('|08   Events fired: ' . Config::setting('discord_events', ''));
        return $f->footer('↑↓ move  ·  ENTER toggle  ·  A add · T test · Q back');
    }

    // ===============================================================
    //  TICKETS (staff view)
    // ===============================================================
    private function tickets(Engine $e, array $in, string $key, string $cmd, array &$st): Frame
    {
        if (($st['view'] ?? '') === 'one') {
            $id = (int) ($st['tid'] ?? 0);
            if ($cmd === 'submit') {
                $body = trim((string) ($in['data']['body'] ?? ''));
                if ($body !== '') {
                    Db::insert('ticket_replies', [
                        'ticket_id' => $id, 'user_id' => $e->session->userId, 'handle' => $e->session->handle(),
                        'is_staff' => 1, 'body' => mb_substr($body, 0, 4000), 'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    Db::update('sysop_tickets', ['status' => 'answered', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);
                    AuditLog::record('admin.ticket.reply', 'ticket', $id, mb_substr($body, 0, 80));
                    Queue::push(Queue::TUBE_DISCORD, ['event' => 'ticket.reply', 'id' => $id, 'staff' => true]);
                }
                return $this->ticketOne($e, $id, $st);
            }
            if ($key === 'R') {
                return Frame::make('form')->title('Staff reply')->header('Reply to ticket #' . $id)->blank()
                    ->form([['name' => 'body', 'label' => 'Reply', 'type' => 'textarea', 'max' => 4000]], 'ENTER sends · ESC cancels');
            }
            if (in_array($key, ['O', 'A', 'C', 'X'], true)) {
                $map = ['O' => 'open', 'A' => 'answered', 'C' => 'closed', 'X' => 'pending'];
                Db::update('sysop_tickets', [
                    'status' => $map[$key],
                    'updated_at' => date('Y-m-d H:i:s'),
                    'closed_at' => $key === 'C' ? date('Y-m-d H:i:s') : null,
                ], ['id' => $id]);
                AuditLog::record('admin.ticket.status', 'ticket', $id, $map[$key]);
            }
            if ($key === 'Q' || $key === "\x1B") {
                $st['view'] = '';
                return $this->tickets($e, [], '', '', $st);
            }
            return $this->ticketOne($e, $id, $st);
        }
        if ($f = $this->back($e, $key, $st)) {
            return $f;
        }
        $filter = $st['filter'] ?? 'open';
        if ($key === 'F') {
            $order = ['open', 'pending', 'answered', 'closed', 'all'];
            $st['filter'] = $order[(array_search($filter, $order, true) + 1) % count($order)];
            $filter = $st['filter'];
        }
        $where = $filter === 'all' ? '1' : 'status = ' . Db::pdo()->quote($filter);
        $rows = Db::all("SELECT * FROM sysop_tickets WHERE $where ORDER BY updated_at DESC LIMIT " . \Bbs\Bbs\Frame::pageSize(7));
        if (ctype_digit($key) && $key !== '0') {
            $idx = (int) $key - 1;
            if (isset($rows[$idx])) {
                $st['view'] = 'one';
                $st['tid'] = (int) $rows[$idx]['id'];
                return $this->ticketOne($e, $st['tid'], $st);
            }
        }
        $f = Frame::make('screen')->title('Tickets')->mode('menu')->header('SysOp · Tickets', 'filter: ' . $filter)->blank();
        $choices = [];
        foreach ($rows as $i => $r) {
            $sc = match ($r['status']) { 'open' => '|14', 'pending' => '|13', 'answered' => '|10', default => '|08' };
            $choices[] = [
                'key'   => (string) ($i + 1),
                'label' => $sc . sprintf('%-9s |11%-14s |07%s', $r['status'], mb_substr($r['handle'], 0, 14), mb_substr($r['subject'], 0, 44)),
                'desc'  => 'updated ' . date('m/d H:i', strtotime($r['updated_at'])),
            ];
        }
        $this->picker($f, $choices);
        if (!$rows) {
            $f->pipe('|08   No tickets in this filter.');
        }
        return $f->footer('↑↓ move  ·  ENTER open  ·  F cycle filter · Q back');
    }

    private function ticketOne(Engine $e, int $id, array &$st): Frame
    {
        $t = Db::one('SELECT * FROM sysop_tickets WHERE id = ?', [$id]);
        if (!$t) {
            $st['view'] = '';
            return $this->tickets($e, [], '', '', $st);
        }
        $f = Frame::make('screen')->title('Ticket #' . $id)->mode('menu')
            ->header('Ticket #' . $id . ' · ' . strtoupper($t['status']), $t['handle'] . ' · ' . $t['ip_phone'])->blank()
            ->pipe('|15   ' . $t['subject'])->rule()
            ->pipe('|08   ' . $t['created_at'] . '  |11' . $t['handle']);
        foreach (explode("\n", wordwrap($t['body'], 112, "\n", true)) as $l) {
            $f->pipe('|07   ' . $l);
        }
        foreach (Db::all('SELECT * FROM ticket_replies WHERE ticket_id = ? ORDER BY created_at', [$id]) as $r) {
            $f->blank()->pipe('|08   ' . $r['created_at'] . '  ' . ($r['is_staff'] ? '|12' : '|11') . $r['handle'] . ($r['is_staff'] ? ' (staff)' : ''));
            foreach (explode("\n", wordwrap($r['body'], 112, "\n", true)) as $l) {
                $f->pipe(($r['is_staff'] ? '|10' : '|07') . '   ' . $l);
            }
        }
        return $f->footer('R reply · O open · X pending · A answered · C close · Q back');
    }

    // ===============================================================
    //  AUDIT LOG
    // ===============================================================
    private function audit(Engine $e, array $in, string $key, array &$st): Frame
    {
        if ($f = $this->back($e, $key, $st)) {
            return $f;
        }
        $st['page'] ??= 0;
        if ($key === 'N' || $key === 'RIGHT') {
            $st['page']++;
        }
        if (($key === 'P' || $key === 'LEFT') && $st['page'] > 0) {
            $st['page']--;
        }
        $per = Frame::pageSize(7);
        $rows = Db::all(
            'SELECT * FROM audit_log ORDER BY id DESC LIMIT ' . $per . ' OFFSET ' . ($st['page'] * $per)
        );
        $total = (int) Db::val('SELECT COUNT(*) FROM audit_log');
        $f = Frame::make('screen')->title('Audit Log')->mode('menu')
            ->header('SysOp · Audit Log', 'page ' . ($st['page'] + 1) . ' · ' . number_format($total) . ' events')->blank();
        $f->pipe('|08   ' . str_pad('WHEN', 18) . str_pad('ACTOR', 15) . str_pad('IP ADDRESS', 17) . str_pad('ACTION', 22) . 'DETAIL');
        $f->rule();
        $detW = max(20, Frame::width() - 3 - 18 - 15 - 17 - 22);
        foreach ($rows as $r) {
            $f->pipe(sprintf(
                '|08   %-18s|11%-15s|10%-17s|09%-22s|07%s',
                date('Y-m-d H:i', strtotime($r['created_at'])),
                mb_substr($r['actor_handle'], 0, 14),
                mb_substr($r['ip'] ?: '—', 0, 16),
                mb_substr($r['action'], 0, 21),
                mb_substr(($r['summary'] ?: $r['target_type'] . ':' . $r['target_id']), 0, $detW)
            ));
        }
        if (!$rows) {
            $f->pipe('|08   nothing logged yet');
        }
        return $f->footer('N / P page · Q back');
    }

    // ===============================================================
    //  CALL LOG
    // ===============================================================
    private function calls(Engine $e, array $in, string $key, array &$st): Frame
    {
        if ($f = $this->back($e, $key, $st)) {
            return $f;
        }
        $st['page'] ??= 0;
        if ($key === 'N') {
            $st['page']++;
        }
        if ($key === 'P' && $st['page'] > 0) {
            $st['page']--;
        }
        $per = Frame::pageSize(7);
        $rows = Db::all('SELECT * FROM call_log ORDER BY id DESC LIMIT ' . $per . ' OFFSET ' . ($st['page'] * $per));
        $total = (int) Db::val('SELECT COUNT(*) FROM call_log');
        $f = Frame::make('screen')->title('Call Log')->mode('menu')
            ->header('SysOp · Call Log', 'page ' . ($st['page'] + 1) . ' · ' . number_format($total) . ' calls')->blank();
        $f->pipe('|08   ' . str_pad('CONNECTED', 18) . str_pad('NODE', 5) . str_pad('CALLER', 15)
            . str_pad('IP ADDRESS', 17) . str_pad('DIALED FROM', 17) . str_pad('SECS', 7) . 'PGS');
        $f->rule();
        foreach ($rows as $r) {
            $f->pipe(sprintf(
                '|08   %-18s|14%-5s|15%-15s|10%-17s|08%-17s|07%-7s%s',
                date('Y-m-d H:i', strtotime($r['connected_at'])),
                (string) $r['node'],
                mb_substr($r['handle'], 0, 14),
                mb_substr($r['ip'] ?: '—', 0, 16),
                $r['ip_phone'],
                $r['seconds'] !== null ? (string) $r['seconds'] : 'live',
                (string) $r['pages']
            ));
        }
        if (!$rows) {
            $f->pipe('|08   no calls logged yet');
        }
        return $f->footer('N / P page · Q back');
    }

    // ===============================================================
    //  MESSAGE ADMIN
    // ===============================================================
    private function messages(Engine $e, array $in, string $key, string $cmd, array &$st): Frame
    {
        if ($cmd === 'submit' && ($st['view'] ?? '') === 'del') {
            $id = (int) ($in['data']['id'] ?? 0);
            if ($id && Db::val('SELECT 1 FROM messages WHERE id = ?', [$id])) {
                Db::update('messages', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => $id]);
                AuditLog::record('admin.message.delete', 'message', $id, 'soft-deleted');
            }
            $st['view'] = '';
            return $this->messages($e, ['cmd' => 'render'], '', '', $st)->sound('beep');
        }
        if ($f = $this->back($e, $key, $st)) {
            return $f;
        }
        if ($key === 'D') {
            $st['view'] = 'del';
            return Frame::make('form')->title('Delete message')->header('Delete a message by ID')->blank()
                ->form([['name' => 'id', 'label' => 'Message ID', 'type' => 'text', 'max' => 12]], 'ENTER · ESC');
        }
        $boards = Db::all('SELECT b.*, c.name AS conf FROM boards b JOIN conferences c ON c.id=b.conference_id ORDER BY c.sort, b.sort');
        $f = Frame::make('screen')->title('Message Admin')->mode('menu')->header('SysOp · Message Base')->blank();
        $f->pipe('|08   ' . str_pad('CONFERENCE / BOARD', 40) . str_pad('POSTS', 8) . 'LAST POST');
        $f->rule();
        foreach ($boards as $b) {
            $f->pipe(sprintf('|09   %-16s|14%-24s|07%-7d|08%s', mb_substr($b['conf'], 0, 15), mb_substr($b['name'], 0, 23), (int) $b['post_count'], $b['last_post_at'] ?: '—'));
        }
        $del = (int) Db::val('SELECT COUNT(*) FROM messages WHERE deleted_at IS NOT NULL');
        $f->blank()->pipe('|08   Soft-deleted messages: ' . $del);
        return $f->footer('D delete a message by ID · Q back');
    }

    // ===============================================================
    //  FILE ADMIN (approval queue)
    // ===============================================================
    private function files(Engine $e, array $in, string $key, string $cmd, array &$st): Frame
    {
        if ($f = $this->back($e, $key, $st)) {
            return $f;
        }
        $queue = Db::all('SELECT f.*, a.name AS area FROM files f JOIN file_areas a ON a.id=f.area_id WHERE f.is_approved = 0 AND f.deleted_at IS NULL ORDER BY f.id');
        if (ctype_digit($key) && $key !== '0') {
            $idx = (int) $key - 1;
            if (isset($queue[$idx])) {
                $st['sel'] = (int) $queue[$idx]['id'];
            }
        }
        if (isset($st['sel']) && in_array($key, ['A', 'X'], true)) {
            if ($key === 'A') {
                Db::update('files', ['is_approved' => 1, 'approved_at' => date('Y-m-d H:i:s')], ['id' => (int) $st['sel']]);
                AuditLog::record('admin.file.approve', 'file', (int) $st['sel'], 'approved');
            } else {
                Db::update('files', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => (int) $st['sel']]);
                AuditLog::record('admin.file.reject', 'file', (int) $st['sel'], 'rejected');
            }
            unset($st['sel']);
            return $this->files($e, ['cmd' => 'render'], '', '', $st)->sound('beep');
        }
        $f = Frame::make('screen')->title('File Admin')->mode('menu')->header('SysOp · Upload Queue', count($queue) . ' pending')->blank();
        $choices = [];
        foreach ($queue as $i => $q) {
            $selMark = (($st['sel'] ?? 0) === (int) $q['id']) ? '|12> ' : '';
            $choices[] = [
                'key'   => (string) ($i + 1),
                'label' => $selMark . '|14' . mb_substr($q['filename'], 0, 28) . '  |08' . mb_substr($q['area'], 0, 14)
                    . '  |11' . mb_substr($q['uploader_handle'], 0, 12),
                'desc'  => number_format((int) $q['size_bytes']) . ' bytes'
                    . ((($st['sel'] ?? 0) === (int) $q['id']) ? ' · selected' : ''),
            ];
        }
        $this->picker($f, $choices);
        if (!$queue) {
            $f->pipe('|10   Upload queue is empty.');
        }
        $areas = Db::all('SELECT name, file_count, min_upload_rank FROM file_areas ORDER BY sort');
        $f->blank()->pipe('|14   AREAS');
        foreach ($areas as $a) {
            $f->pipe(sprintf('|08   %-24s %4d files   upload rank >= %d', $a['name'], (int) $a['file_count'], (int) $a['min_upload_rank']));
        }
        return $f->footer('↑↓ move  ·  ENTER select  ·  A approve · X reject · Q back');
    }

    // ===============================================================
    //  POLLS
    // ===============================================================
    private function polls(Engine $e, array $in, string $key, string $cmd, array &$st): Frame
    {
        if ($cmd === 'submit' && ($st['view'] ?? '') === 'new') {
            $d = (array) ($in['data'] ?? []);
            $q = trim((string) ($d['question'] ?? ''));
            $opts = array_values(array_filter(array_map('trim', explode("\n", (string) ($d['options'] ?? '')))));
            if ($q !== '' && count($opts) >= 2) {
                $pid = Db::insert('polls', ['question' => mb_substr($q, 0, 200), 'is_open' => 1, 'created_by' => $e->session->userId, 'created_at' => date('Y-m-d H:i:s')]);
                foreach ($opts as $i => $o) {
                    Db::insert('poll_options', ['poll_id' => $pid, 'label' => mb_substr($o, 0, 120), 'sort' => ($i + 1) * 10]);
                }
                AuditLog::record('admin.poll.new', 'poll', $pid, $q);
            }
            $st['view'] = '';
            return $this->polls($e, ['cmd' => 'render'], '', '', $st)->sound('beep');
        }
        if ($f = $this->back($e, $key, $st)) {
            return $f;
        }
        if ($key === 'N') {
            $st['view'] = 'new';
            return Frame::make('form')->title('New Poll')->header('Create a poll')->blank()
                ->form([
                    ['name' => 'question', 'label' => 'Question', 'type' => 'text', 'max' => 200],
                    ['name' => 'options', 'label' => 'Options (one per line, 2-9)', 'type' => 'textarea', 'max' => 1000],
                ], 'ENTER creates · ESC cancels');
        }
        $polls = Db::all('SELECT p.*, (SELECT COUNT(*) FROM poll_votes v WHERE v.poll_id=p.id) AS votes FROM polls p ORDER BY p.id DESC');
        if (ctype_digit($key) && $key !== '0') {
            $idx = (int) $key - 1;
            if (isset($polls[$idx])) {
                $p = $polls[$idx];
                Db::update('polls', ['is_open' => $p['is_open'] ? 0 : 1], ['id' => $p['id']]);
                AuditLog::record('admin.poll.toggle', 'poll', (int) $p['id'], $p['is_open'] ? 'closed' : 'reopened');
            }
        }
        $f = Frame::make('screen')->title('Polls')->mode('menu')->header('SysOp · Voting Booths')->blank();
        $choices = [];
        foreach ($polls as $i => $p) {
            $on = $p['is_open'] ? '|10OPEN  ' : '|08CLOSED';
            $choices[] = [
                'key'   => (string) ($i + 1),
                'label' => $on . ' |07' . mb_substr($p['question'], 0, 70),
                'desc'  => (int) $p['votes'] . ' votes · ' . ($p['is_open'] ? 'open' : 'closed'),
            ];
        }
        $this->picker($f, $choices);
        return $f->footer('↑↓ move  ·  ENTER open/close  ·  N new poll · Q back');
    }
}
