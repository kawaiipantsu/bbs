<?php
/**
 * THUGS(red) BBS - set / reset a user's password from the shell.
 *
 * SysOp tool for when someone is locked out, or to rotate the seeded
 * `sysop` account after install. Passwords are bcrypt-hashed (weak ones
 * are allowed by design, 3-char minimum) and never written to the log or
 * the audit trail.
 *
 *   php contrib/set-password.php <handle> <newpassword>
 *   php contrib/set-password.php <handle>              # prompt (hidden), twice
 *   php contrib/set-password.php <handle> --stdin      # read password from stdin
 *   php contrib/set-password.php <handle> --random     # generate one, print it
 *
 * Options:
 *   --random          generate a strong 16-char password and print it once
 *   --stdin           read the new password from STDIN (first line, for scripts)
 *   --force-change    require the user to pick a new password on next login
 *   --logout          drop the user's active sessions so the old one dies now
 *   --yes             skip the confirmation prompt
 *
 * Exit codes: 0 ok · 1 usage · 2 user not found · 3 password rejected
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use Bbs\Admin\AuditLog;
use Bbs\Auth\Password;
use Bbs\Core\Db;

$argv0    = 'contrib/set-password.php';
$out      = static fn (string $m) => fwrite(STDOUT, $m . "\n");
$err      = static fn (string $m) => fwrite(STDERR, $m . "\n");
// probe once, before anything reads STDIN (mid-stream probing warns on pipes)
$stdinTty = (bool) @stream_isatty(STDIN);

/* ---- parse args -------------------------------------------------------- */
$args   = array_slice($argv, 1);
$flags  = [];
$pos    = [];
foreach ($args as $a) {
    if (str_starts_with($a, '--')) {
        $flags[substr($a, 2)] = true;
    } else {
        $pos[] = $a;
    }
}

$handle = $pos[0] ?? '';
$plain  = $pos[1] ?? null;

if ($handle === '' || isset($flags['help']) || isset($flags['h'])) {
    $err("usage: php $argv0 <handle> [<newpassword>] [--random] [--stdin] [--force-change] [--logout] [--yes]");
    exit(1);
}

/* ---- find the user --------------------------------------------------- */
$user = Db::one(
    'SELECT id, handle, status, must_change_password
       FROM users
      WHERE LOWER(handle) = LOWER(?) AND deleted_at IS NULL',
    [$handle]
);
if (!$user) {
    $err("No such user: $handle");
    exit(2);
}

/* ---- work out the new password ------------------------------------- */
$generated = false;
if (isset($flags['random'])) {
    // readable-ish: no ambiguous chars, mixed classes
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789#%+=?';
    $plain = '';
    for ($i = 0; $i < 16; $i++) {
        $plain .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    $generated = true;
} elseif (isset($flags['stdin'])) {
    $plain = rtrim((string) fgets(STDIN), "\r\n");
} elseif ($plain === null) {
    $plain = prompt_hidden('New password: ', $stdinTty);
    $again = prompt_hidden('Repeat password: ', $stdinTty);
    if ($plain !== $again) {
        $err('Passwords did not match.');
        exit(3);
    }
}

$plain = (string) $plain;
if (($why = Password::validationError($plain)) !== null) {
    $err($why);
    exit(3);
}

/* ---- confirm ------------------------------------------------------- */
if (!isset($flags['yes'])) {
    $out("User    : {$user['handle']}  (id {$user['id']}, status {$user['status']})");
    $out('Action  : replace password hash'
        . (isset($flags['force-change']) ? ', force change on next login' : '')
        . (isset($flags['logout']) ? ', end active sessions' : ''));
    fwrite(STDOUT, 'Proceed? [y/N] ');
    $ans = strtolower(trim((string) fgets(STDIN)));
    if ($ans !== 'y' && $ans !== 'yes') {
        $out('Aborted.');
        exit(0);
    }
}

/* ---- apply ------------------------------------------------------------ */
$forceChange = isset($flags['force-change']) ? 1 : 0;

Db::update('users', [
    'password_hash'        => Password::hash($plain),
    'must_change_password' => $forceChange,
    'updated_at'           => date('Y-m-d H:i:s'),
], ['id' => $user['id']]);

$killed = 0;
if (isset($flags['logout'])) {
    $killed = Db::q('DELETE FROM sessions WHERE user_id = ?', [$user['id']])->rowCount();
}

/* ---- audit (no password, ever) ------------------------------------- */
$who = trim((string) (getenv('SUDO_USER') ?: getenv('USER') ?: getenv('LOGNAME') ?: 'shell'));
AuditLog::system(
    'user.password_set',
    "shell password reset for {$user['handle']} by {$who}",
    [
        'user_id'       => (int) $user['id'],
        'handle'        => $user['handle'],
        'by'            => $who,
        'force_change'  => (bool) $forceChange,
        'sessions_ended' => $killed,
        'generated'     => $generated,
    ]
);

/* ---- report ------------------------------------------------------- */
$out("OK - password updated for {$user['handle']}.");
if ($generated) {
    $out('');
    $out("    new password:  $plain");
    $out('');
    $out('    (shown once - copy it now)');
}
if ($forceChange) {
    $out('    user must set a new password on next login.');
}
if (isset($flags['logout'])) {
    $out("    active sessions ended: $killed");
}
exit(0);

/* --------------------------------------------------------------------- */
function prompt_hidden(string $label, bool $tty): string
{
    fwrite(STDOUT, $label);
    if ($tty && function_exists('shell_exec')) {
        @shell_exec('stty -echo 2>/dev/null');
    }
    $line = rtrim((string) fgets(STDIN), "\r\n");
    if ($tty && function_exists('shell_exec')) {
        @shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, "\n");
    }
    return $line;
}
