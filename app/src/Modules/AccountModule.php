<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Admin\AuditLog;
use Bbs\Auth\Auth;
use Bbs\Auth\Rbac;
use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;
use Bbs\Core\Crypto;
use Bbs\Core\Db;

/**
 * "Account" - the caller's own profile, settings and password.
 */
final class AccountModule extends Module
{
    public static function slugs(): array
    {
        return ['account'];
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        $user = $e->session->user();
        if (!$user) {
            return Frame::make('screen')->title('Account')->mode('menu')->header('Account')->blank()
                ->pipe('|11   You are browsing as a guest.')
                ->pipe('|07   Log off and choose [N]ew user to get an account, or [L]og in.')
                ->footer('Q back');
        }

        $key = strtoupper((string) ($in['key'] ?? ''));
        $cmd = (string) ($in['cmd'] ?? '');
        $mode = $st['mode'] ?? 'view';

        if ($mode === 'edit') {
            if ($cmd === 'cancel' || $key === "\x1B") {
                $st['mode'] = 'view';
                return $this->view($e);
            }
            if ($cmd === 'submit') {
                $d = (array) ($in['data'] ?? []);
                Db::update('users', [
                    'tagline'   => mb_substr(trim((string) ($d['tagline'] ?? '')), 0, 120),
                    'location'  => mb_substr(trim((string) ($d['location'] ?? '')), 0, 80),
                    'signature' => mb_substr(trim((string) ($d['signature'] ?? '')), 0, 480),
                ], ['id' => $user['id']]);
                if (($d['email'] ?? '') !== '') {
                    $email = trim((string) $d['email']);
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        Db::q(
                            'INSERT INTO user_secrets (user_id, email_cipher, email_index)
                             VALUES (?,?,?) ON DUPLICATE KEY UPDATE email_cipher = VALUES(email_cipher), email_index = VALUES(email_index)',
                            [$user['id'], Crypto::encrypt($email), Crypto::blindIndex($email)]
                        );
                    }
                }
                AuditLog::record('account.update', 'user', (int) $user['id'], 'profile edited');
                $st['mode'] = 'view';
                return $this->view($e)->sound('beep');
            }
            $sec = Db::one('SELECT email_cipher FROM user_secrets WHERE user_id = ?', [$user['id']]);
            $email = $sec && $sec['email_cipher'] ? (Crypto::decrypt($sec['email_cipher']) ?? '') : '';
            return Frame::make('form')->title('Edit Profile')->header('Edit Profile')->blank()
                ->form([
                    ['name' => 'tagline', 'label' => 'Tagline', 'type' => 'text', 'max' => 120, 'value' => $user['tagline']],
                    ['name' => 'location', 'label' => 'Location', 'type' => 'text', 'max' => 80, 'value' => $user['location']],
                    ['name' => 'signature', 'label' => 'Signature (shown under posts)', 'type' => 'textarea', 'max' => 480, 'value' => $user['signature']],
                    ['name' => 'email', 'label' => 'E-mail (encrypted, SysOp-only)', 'type' => 'text', 'max' => 190, 'value' => $email],
                ], 'ENTER saves · ESC cancels');
        }

        if ($mode === 'passwd') {
            if ($cmd === 'cancel' || $key === "\x1B") {
                $st['mode'] = 'view';
                return $this->view($e);
            }
            if ($cmd === 'submit') {
                $d = (array) ($in['data'] ?? []);
                if (($d['p1'] ?? '') !== ($d['p2'] ?? '')) {
                    return $this->pwForm('New passwords did not match.')->sound('error');
                }
                $res = Auth::changePassword($e->session, (string) ($d['cur'] ?? ''), (string) ($d['p1'] ?? ''));
                if (!$res['ok']) {
                    return $this->pwForm($res['error'])->sound('error');
                }
                $st['mode'] = 'view';
                return $this->view($e)->sound('beep')->pipe('|10   Password changed.');
            }
            return $this->pwForm();
        }

        // view
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }
        if ($key === 'E') {
            $st['mode'] = 'edit';
            return $this->run($e, $slug, ['cmd' => 'render'], $st);
        }
        if ($key === 'P') {
            $st['mode'] = 'passwd';
            return $this->pwForm();
        }
        return $this->view($e);
    }

    private function view(Engine $e): Frame
    {
        $u = $e->session->user();
        $roles = Rbac::rolesOf((int) $u['id']);
        $perms = Rbac::permissions($u);
        $f = Frame::make('screen')->title('Account')->mode('menu')
            ->header('Account: ' . $u['handle'])->blank();
        $f->pipe('|07   Handle .........: |15' . $u['handle']);
        $f->pipe('|07   Roles ..........: |11' . (implode(', ', array_column($roles, 'name')) ?: 'User'));
        $f->pipe('|07   Tagline ........: |07' . ($u['tagline'] ?: '|08(none)'));
        $f->pipe('|07   Location .......: |07' . ($u['location'] ?: '|08(none)'));
        $f->pipe('|07   Member since ...: |07' . date('Y-m-d', strtotime($u['created_at'])));
        $f->pipe('|07   Last call ......: |07' . ($u['last_login_at'] ? date('Y-m-d H:i', strtotime($u['last_login_at'])) . ' from ' . $u['last_login_phone'] : 'this one'));
        $f->pipe(sprintf('|07   Calls / Posts ..: |14%d|07 / |14%d', $u['calls'], $u['posts']));
        $f->pipe(sprintf('|07   Up / Downloads .: |14%d|07 / |14%d', $u['uploads'], $u['downloads']));
        $f->blank()->pipe('|08   Permissions: ' . implode(' ', $perms));
        if ($u['signature']) {
            $f->blank()->pipe('|08   --- signature ---');
            foreach (explode("\n", $u['signature']) as $l) {
                $f->pipe('|07   ' . $l);
            }
        }
        return $f->footer('E edit profile · P change password · Q back');
    }

    private function pwForm(string $err = ''): Frame
    {
        return Frame::make('form')->title('Change Password')->header('Change Password')->blank()
            ->pipe($err ? '|12   ' . $err : '|08   Short passwords are fine. 3 characters minimum.')
            ->form([
                ['name' => 'cur', 'label' => 'Current password', 'type' => 'password', 'max' => 200],
                ['name' => 'p1', 'label' => 'New password', 'type' => 'password', 'max' => 200],
                ['name' => 'p2', 'label' => 'Repeat new password', 'type' => 'password', 'max' => 200],
            ], 'ENTER saves · ESC cancels');
    }
}
