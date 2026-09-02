<?php

declare(strict_types=1);

namespace Bbs\Bbs;

use Bbs\Admin\AuditLog;
use Bbs\Auth\Auth;
use Bbs\Auth\Rbac;
use Bbs\Auth\Session;
use Bbs\Core\Config;
use Bbs\Core\Db;
use Bbs\Core\Request;
use Bbs\Modules\AccountModule;
use Bbs\Modules\AdminModule;
use Bbs\Modules\ChatModule;
use Bbs\Modules\CommunityModule;
use Bbs\Modules\FilesModule;
use Bbs\Modules\GamesModule;
use Bbs\Modules\MessagesModule;
use Bbs\Modules\NewsModule;
use Bbs\Modules\PollModule;
use Bbs\Modules\StatsModule;
use Bbs\Modules\TicketModule;

/**
 * The BBS state machine. Holds a navigation stack in the session and turns
 * client input ({key,input,cmd,data}) into rendered Frames.
 */
final class Engine
{
    /** @var array{stack:list<array<string,mixed>>} */
    private array $state;

    /** @var array<string,scalar|null> */
    public array $ctx;

    /** @var array<string,class-string<Module>> */
    private const MODULES = [
        MessagesModule::class,
        FilesModule::class,
        NewsModule::class,
        CommunityModule::class,
        StatsModule::class,
        TicketModule::class,
        PollModule::class,
        GamesModule::class,
        ChatModule::class,
        AccountModule::class,
        AdminModule::class,
    ];

    /** @var array<string,Module> resolved lazily: slug => instance */
    private array $moduleCache = [];

    public function __construct(
        public readonly Session $session,
        public readonly Request $request,
    ) {
        $s = $session->get('bbs');
        $this->state = is_array($s) && isset($s['stack']) ? $s : ['stack' => [['t' => 'menu', 'ref' => 'main']]];
        $this->ctx = Context::for($session);
        AuditLog::bind($session);
    }

    public function user(): ?array
    {
        return $this->session->user();
    }

    public function can(string $perm): bool
    {
        return Rbac::can($this->user(), $perm);
    }

    public function rank(): int
    {
        return Rbac::rank($this->user());
    }

    public function guest(): bool
    {
        return $this->session->isGuest();
    }

    // -----------------------------------------------------------------
    //  Boot: called by /api/session right after "connect"
    // -----------------------------------------------------------------
    public function boot(): array
    {
        $this->state = ['stack' => [['t' => 'motd']]];
        $this->save();
        return $this->render();
    }

    // -----------------------------------------------------------------
    //  Dispatch one step
    // -----------------------------------------------------------------
    public function dispatch(array $in): array
    {
        $key = strtoupper((string) ($in['key'] ?? ''));
        $cmd = (string) ($in['cmd'] ?? '');
        $node = $this->current();

        // plain redraw request (client reconnect / post-redirect refresh)
        if ($cmd === 'render' || $cmd === 'noop' || ($key === '' && $cmd === '' && ($in['input'] ?? '') === '')) {
            $this->bumpCall('page');
            return $this->finish($this->renderCurrentFrame());
        }

        // force password change gate
        if ($this->passwordChangeRequired() && ($node['t'] ?? '') !== 'changepw') {
            $this->replaceStack([['t' => 'changepw', 'st' => []]]);
            return $this->render();
        }

        $frame = match ($node['t']) {
            'motd'     => $this->stepMotd(),
            'auth'     => $this->stepAuth($in, $key, $cmd),
            'changepw' => $this->stepChangePw($in, $cmd),
            'menu'     => $this->stepMenu($node['ref'], $key),
            'screen'   => $this->stepScreen($key),
            'logoff'   => $this->stepLogoff($key, $cmd),
            'module'   => $this->stepModule($node, $in),
            default    => $this->toMenuFrame('main'),
        };

        $this->save();
        $this->bumpCall('action');
        return $frame instanceof Frame ? $this->finish($frame) : $frame;
    }

    /** Re-render current state (used on reconnect / redraw). */
    public function render(): array
    {
        $node = $this->current();
        $frame = match ($node['t']) {
            'motd'     => $this->renderMotd(),
            'auth'     => $this->renderAuth(),
            'changepw' => $this->renderChangePw(),
            'menu'     => $this->toMenuFrame($node['ref']),
            'screen'   => $this->renderScreen($node['ref']),
            'logoff'   => $this->renderLogoff(),
            'module'   => $this->renderModule($node),
            default    => $this->toMenuFrame('main'),
        };
        $this->bumpCall('page');
        return $this->finish($frame);
    }

    // -----------------------------------------------------------------
    //  MOTD
    // -----------------------------------------------------------------
    private function renderMotd(): Frame
    {
        $slug = Config::setting('motd_screen', 'boot.motd');
        $f = Screen::render(Frame::make('screen'), $slug, $this->ctx, 'Message of the Day');
        $f->mode('pager')->meta(['boot' => true]);
        return $f;
    }

    private function stepMotd(): Frame
    {
        if ($this->session->isGuest() && !Config::bool('guest_browsing', true)) {
            $this->replaceStack([['t' => 'auth', 'st' => ['step' => 'menu']]]);
            return $this->renderAuth();
        }
        if ($this->session->isGuest()) {
            $this->replaceStack([['t' => 'auth', 'st' => ['step' => 'menu']]]);
            return $this->renderAuth();
        }
        $this->replaceStack([['t' => 'menu', 'ref' => 'main']]);
        return $this->toMenuFrame('main');
    }

    // -----------------------------------------------------------------
    //  Auth flow
    // -----------------------------------------------------------------
    private function renderAuth(): Frame
    {
        $st = $this->current()['st'] ?? ['step' => 'menu'];
        $step = $st['step'] ?? 'menu';

        if ($step === 'login') {
            return Frame::make('form')->title('Log In')->header('Log In')->blank()
                ->pipe('|07   Enter your handle and password.')
                ->pipe($st['error'] ?? '' ? '|12   ' . $st['error'] : '|08   Weak passwords are fine here.')
                ->form([
                    ['name' => 'handle', 'label' => 'Handle', 'type' => 'text', 'max' => 32],
                    ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'max' => 200],
                ], 'ENTER submits · ESC cancels');
        }
        if ($step === 'register') {
            return Frame::make('form')->title('New User')->header('New User Application')->blank()
                ->block(Screen::load('newuser.form')['body'] ?? '', 'pipe', $this->ctx)
                ->pipe($st['error'] ?? '' ? '|12   ' . $st['error'] : '')
                ->form([
                    ['name' => 'handle', 'label' => 'Choose a handle', 'type' => 'text', 'max' => 32],
                    ['name' => 'password', 'label' => 'Choose a password', 'type' => 'password', 'max' => 200],
                    ['name' => 'password2', 'label' => 'Repeat password', 'type' => 'password', 'max' => 200],
                    ['name' => 'email', 'label' => 'E-mail (optional, encrypted)', 'type' => 'text', 'max' => 190],
                ], 'ENTER submits · ESC cancels');
        }

        return Screen::render(Frame::make('screen'), 'auth.welcome', $this->ctx, 'Welcome')
            ->view('menu')->mode('menu')->title('Welcome')->prompt('Choose')
            ->meta(['keys' => ['L', 'N', 'G', 'Q']]);
    }

    private function stepAuth(array $in, string $key, string $cmd): Frame
    {
        $node = &$this->state['stack'][count($this->state['stack']) - 1];
        $node['st'] ??= ['step' => 'menu'];
        $step = $node['st']['step'];

        if ($step === 'menu') {
            return match ($key) {
                'L' => $this->setAuthStep('login'),
                'N' => Config::bool('registration_open', true)
                        ? $this->setAuthStep('register')
                        : $this->renderAuth()->pipe('|12   Registration is closed.'),
                'G' => $this->enterAsGuest(),
                'Q', "\x1B" => $this->beginLogoff(),
                default => $this->renderAuth(),
            };
        }

        if ($cmd === 'cancel' || $key === "\x1B") {
            $node['st'] = ['step' => 'menu'];
            return $this->renderAuth();
        }

        if ($cmd === 'submit') {
            $data = (array) ($in['data'] ?? []);
            if ($step === 'login') {
                $res = Auth::login($this->session, (string) ($data['handle'] ?? ''), (string) ($data['password'] ?? ''));
                if (!$res['ok']) {
                    $node['st'] = ['step' => 'login', 'error' => $res['error']];
                    return $this->renderAuth()->sound('error');
                }
                $this->afterLogin();
                return $this->currentFrameAfterLogin()->sound('connect');
            }
            if ($step === 'register') {
                if (($data['password'] ?? '') !== ($data['password2'] ?? '')) {
                    $node['st'] = ['step' => 'register', 'error' => 'Passwords did not match.'];
                    return $this->renderAuth()->sound('error');
                }
                $res = Auth::register(
                    $this->session,
                    (string) ($data['handle'] ?? ''),
                    (string) ($data['password'] ?? ''),
                    (string) ($data['email'] ?? '')
                );
                if (!$res['ok']) {
                    $node['st'] = ['step' => 'register', 'error' => $res['error']];
                    return $this->renderAuth()->sound('error');
                }
                $this->afterLogin();
                return $this->currentFrameAfterLogin()->sound('connect');
            }
        }
        return $this->renderAuth();
    }

    private function setAuthStep(string $step): Frame
    {
        $this->state['stack'][count($this->state['stack']) - 1]['st'] = ['step' => $step];
        return $this->renderAuth();
    }

    private function enterAsGuest(): Frame
    {
        AuditLog::record('auth.guest', 'session', $this->session->id, 'entered as guest');
        $this->replaceStack([['t' => 'menu', 'ref' => 'main']]);
        return $this->toMenuFrame('main');
    }

    private function afterLogin(): void
    {
        $this->ctx = Context::for($this->session);
        Db::update('call_log', [
            'user_id' => $this->session->userId,
            'handle'  => $this->session->handle(),
        ], ['session_id' => $this->session->id]);
    }

    private function currentFrameAfterLogin(): Frame
    {
        if ($this->passwordChangeRequired()) {
            $this->replaceStack([['t' => 'changepw', 'st' => []]]);
            return $this->renderChangePw();
        }
        $this->replaceStack([['t' => 'menu', 'ref' => 'main']]);
        return $this->toMenuFrame('main');
    }

    // -----------------------------------------------------------------
    //  Forced password change
    // -----------------------------------------------------------------
    private function passwordChangeRequired(): bool
    {
        $u = $this->user();
        return $u && (int) $u['must_change_password'] === 1;
    }

    private function renderChangePw(array $error = []): Frame
    {
        return Frame::make('form')->title('Change Password')->header('Change Password')->blank()
            ->pipe('|11   Your account requires a new password before you can continue.')
            ->pipe($error ? '|12   ' . $error[0] : '|08   Pick anything you can remember. 3 characters minimum.')
            ->form([
                ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'max' => 200],
                ['name' => 'password2', 'label' => 'Repeat it', 'type' => 'password', 'max' => 200],
            ], 'ENTER submits');
    }

    private function stepChangePw(array $in, string $cmd): Frame
    {
        if ($cmd !== 'submit') {
            return $this->renderChangePw();
        }
        $data = (array) ($in['data'] ?? []);
        if (($data['password'] ?? '') !== ($data['password2'] ?? '')) {
            return $this->renderChangePw(['Passwords did not match.'])->sound('error');
        }
        $res = Auth::changePassword($this->session, '', (string) ($data['password'] ?? ''));
        if (!$res['ok']) {
            return $this->renderChangePw([$res['error']])->sound('error');
        }
        $this->replaceStack([['t' => 'menu', 'ref' => 'main']]);
        return $this->toMenuFrame('main')->sound('beep');
    }

    // -----------------------------------------------------------------
    //  Menus
    // -----------------------------------------------------------------
    private function toMenuFrame(string $slug): Frame
    {
        $f = Frame::make('menu');
        Menu::render($f, $slug, $this->session, $this->ctx);
        return $f;
    }

    private function stepMenu(string $slug, string $key): Frame
    {
        if ($key === "\x1B" || $key === 'Q') {
            if ($slug === 'main') {
                return $this->beginLogoff();
            }
            $this->pop();
            return $this->renderCurrentFrame();
        }

        $item = Menu::resolve($slug, $this->session, $key);
        if (!$item) {
            return $this->toMenuFrame($slug)->sound('error');
        }

        return match ($item['action']) {
            'menu'   => $this->pushRender(['t' => 'menu', 'ref' => $item['target']]),
            'screen' => $this->pushRender(['t' => 'screen', 'ref' => $item['target']]),
            'module' => $this->enterModule($item['target']),
            'logoff' => $this->beginLogoff(),
            'url'    => Frame::make('redirect')->mode('redirect')->meta(['url' => $item['target']]),
            default  => $this->toMenuFrame($slug),
        };
    }

    private function enterModule(string $slug): Frame
    {
        $mod = $this->resolveModule($slug);
        if (!$mod) {
            return $this->toMenuFrame($this->parentMenuRef())
                ->sound('error')->pipe('|12   Module "' . $slug . '" not installed.');
        }
        $this->push(['t' => 'module', 'ref' => $slug, 'st' => []]);
        return $this->renderModule($this->current());
    }

    // -----------------------------------------------------------------
    //  Screens
    // -----------------------------------------------------------------
    private function renderScreen(string $slug): Frame
    {
        return Screen::render(Frame::make('screen'), $slug, $this->ctx, 'Screen');
    }

    private function stepScreen(string $key): Frame
    {
        if (in_array($key, ["\x1B", 'Q', "\r", "\n", 'ENTER'], true)) {
            $this->pop();
            return $this->renderCurrentFrame();
        }
        return $this->renderScreen($this->current()['ref']);
    }

    // -----------------------------------------------------------------
    //  Modules
    // -----------------------------------------------------------------
    private function renderModule(array $node): Frame
    {
        $mod = $this->resolveModule($node['ref']);
        if (!$mod) {
            $this->pop();
            return $this->renderCurrentFrame();
        }
        $st = $node['st'] ?? [];
        $f = $mod->run($this, $node['ref'], ['cmd' => 'render'], $st);
        $this->storeModuleState($st);
        return $f;
    }

    private function stepModule(array $node, array $in): Frame
    {
        $mod = $this->resolveModule($node['ref']);
        if (!$mod) {
            $this->pop();
            return $this->renderCurrentFrame();
        }
        $st = $node['st'] ?? [];
        $f = $mod->run($this, $node['ref'], $in, $st);
        $this->storeModuleState($st);
        return $f;
    }

    private function storeModuleState(array $st): void
    {
        $i = count($this->state['stack']) - 1;
        if ($i >= 0 && ($this->state['stack'][$i]['t'] ?? '') === 'module') {
            $this->state['stack'][$i]['st'] = $st;
        }
    }

    // -----------------------------------------------------------------
    //  Logoff
    // -----------------------------------------------------------------
    private function beginLogoff(): Frame
    {
        $this->push(['t' => 'logoff', 'st' => []]);
        return $this->renderLogoff();
    }

    private function renderLogoff(): Frame
    {
        return Frame::make('screen')->title('Log Off')->mode('menu')->prompt('Hang up?')
            ->header('Disconnect')->blank()
            ->pipe('|07   Really hang up the modem, ' . ($this->user()['handle'] ?? 'caller') . '?')
            ->blank()
            ->pipe('|08   [|15Y|08] Yes, disconnect      [|15N|08] No, stay online')
            ->footer('Y / N')
            ->meta(['keys' => ['Y', 'N']]);
    }

    private function stepLogoff(string $key, string $cmd): Frame
    {
        if ($key === 'Y') {
            $ctx = Context::for($this->session);
            $handle = $this->user()['handle'] ?? 'guest';
            $this->closeCall();
            AuditLog::record('auth.logoff', 'session', $this->session->id, "$handle hung up");
            $this->session->logout();
            $this->state = ['stack' => [['t' => 'motd']]];
            $this->save();
            return Screen::render(Frame::make('screen'), 'logoff', array_merge($ctx, [
                'handle'         => $handle,
                'session_time'   => $this->callDuration(),
                'session_pages'  => (string) $this->callPages(),
            ]), 'Goodbye')->mode('pager')->sound('hangup')->meta(['hangup' => true]);
        }
        // N or anything -> back
        $this->pop();
        return $this->renderCurrentFrame();
    }

    // -----------------------------------------------------------------
    //  Navigation helpers
    // -----------------------------------------------------------------
    public function current(): array
    {
        return $this->state['stack'][count($this->state['stack']) - 1] ?? ['t' => 'menu', 'ref' => 'main'];
    }

    public function push(array $node): void
    {
        $this->state['stack'][] = $node;
        if (count($this->state['stack']) > 24) {
            array_splice($this->state['stack'], 1, 1);
        }
    }

    public function pushRender(array $node): Frame
    {
        $this->push($node);
        return $this->renderCurrentFrame();
    }

    public function pop(): void
    {
        if (count($this->state['stack']) > 1) {
            array_pop($this->state['stack']);
        }
    }

    public function replaceStack(array $stack): void
    {
        $this->state['stack'] = $stack;
    }

    /** Modules call this to jump elsewhere (e.g. into a message thread). */
    public function goModule(string $slug, array $st = []): Frame
    {
        $this->push(['t' => 'module', 'ref' => $slug, 'st' => $st]);
        return $this->renderModule($this->current());
    }

    public function goScreen(string $slug): Frame
    {
        return $this->pushRender(['t' => 'screen', 'ref' => $slug]);
    }

    public function goMenu(string $slug): Frame
    {
        return $this->pushRender(['t' => 'menu', 'ref' => $slug]);
    }

    /** Pop this module and redraw whatever is underneath. */
    public function exitModule(): Frame
    {
        $this->pop();
        return $this->renderCurrentFrame();
    }

    public function renderCurrentFrame(): Frame
    {
        $node = $this->current();
        return match ($node['t']) {
            'motd'     => $this->renderMotd(),
            'auth'     => $this->renderAuth(),
            'changepw' => $this->renderChangePw(),
            'menu'     => $this->toMenuFrame($node['ref']),
            'screen'   => $this->renderScreen($node['ref']),
            'logoff'   => $this->renderLogoff(),
            'module'   => $this->renderModule($node),
            default    => $this->toMenuFrame('main'),
        };
    }

    private function parentMenuRef(): string
    {
        for ($i = count($this->state['stack']) - 1; $i >= 0; $i--) {
            if (($this->state['stack'][$i]['t'] ?? '') === 'menu') {
                return $this->state['stack'][$i]['ref'];
            }
        }
        return 'main';
    }

    private function resolveModule(string $slug): ?Module
    {
        if (isset($this->moduleCache[$slug])) {
            return $this->moduleCache[$slug];
        }
        foreach (self::MODULES as $class) {
            if (in_array($slug, $class::slugs(), true)) {
                return $this->moduleCache[$slug] = new $class();
            }
        }
        return null;
    }

    // -----------------------------------------------------------------
    //  Call-log bookkeeping
    // -----------------------------------------------------------------
    private function bumpCall(string $what): void
    {
        $id = $this->session->get('call_id');
        if (!$id) {
            return;
        }
        $col = $what === 'page' ? 'pages' : 'actions';
        try {
            Db::q("UPDATE call_log SET $col = $col + 1 WHERE id = ?", [$id]);
        } catch (\Throwable) {
        }
    }

    private function closeCall(): void
    {
        $id = $this->session->get('call_id');
        if (!$id) {
            return;
        }
        Db::q(
            'UPDATE call_log SET disconnected_at = NOW(),
             seconds = TIMESTAMPDIFF(SECOND, connected_at, NOW()) WHERE id = ?',
            [$id]
        );
    }

    private function callDuration(): string
    {
        $id = $this->session->get('call_id');
        $secs = (int) Db::val('SELECT TIMESTAMPDIFF(SECOND, connected_at, NOW()) FROM call_log WHERE id = ?', [$id]) ?: 0;
        $m = intdiv($secs, 60);
        $s = $secs % 60;
        return sprintf('%dm %02ds', $m, $s);
    }

    private function callPages(): int
    {
        $id = $this->session->get('call_id');
        return (int) Db::val('SELECT pages FROM call_log WHERE id = ?', [$id]);
    }

    // -----------------------------------------------------------------
    public function finishPublic(Frame $frame): array
    {
        return $this->finish($frame);
    }

    private function finish(Frame $frame): array
    {
        $out = $frame->toArray();
        $out['csrf'] = $this->session->csrf();
        $out['whoami'] = [
            'guest'  => $this->session->isGuest(),
            'handle' => $this->session->handle(),
            'rank'   => $this->rank(),
            'node'   => $this->session->node,
            'phone'  => $this->session->ipPhone,
        ];
        return $out;
    }

    public function save(): void
    {
        $this->session->put('bbs', $this->state);
        $this->session->save();
    }
}
