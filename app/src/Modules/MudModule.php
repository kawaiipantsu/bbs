<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;
use Bbs\Mud\Mud;
use Bbs\Mud\Player;

/**
 * Bridges the BBS terminal to Hackers-MUD. Keeps a rolling scrollback in the
 * module sub-state; every keystroke line is fed to Mud::command() and the
 * returned pipe-coded lines are appended. ESC drops back to the Game Room.
 *
 * Audio: each render carries frame.meta.sfx (one-shot effect names queued by
 * the command handlers) and frame.meta.ambient (the looping zone bed key);
 * the client (terminal.js) plays them.
 */
final class MudModule extends Module
{
    private const LOG_CAP = 400;

    public static function slugs(): array
    {
        return ['mud.play'];
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        $key = strtoupper((string) ($in['key'] ?? ''));
        $cmd = (string) ($in['cmd'] ?? '');
        $line = (string) ($in['input'] ?? '');

        if ($key === "\x1B" && $line === '') {
            return $e->exitModule();
        }

        if ($e->guest()) {
            return Frame::make('screen')->view('screen')->title('Hackers-MUD')->mode('menu')
                ->header('Hackers-MUD', 'jack point')->blank()
                ->pipe('|11   NIGHT CITY GRID  ::  ACCESS DENIED')->blank()
                ->pipe('|07   Hackers-MUD runs off your BBS account. Guests cannot jack in.')
                ->pipe('|08   Log in or register (main menu), then come back.')
                ->footer('Q / ESC  back')->meta(['keys' => ['Q']]);
        }

        $uid = (int) $e->session->userId;
        $handle = $e->session->handle() ?: 'runner';
        $sfx = [];

        // ---- first entry / reconnect bootstrap -------------------------
        if (!isset($st['phase'])) {
            Mud::takeSfx();   // clear anything stale
            $open = Mud::open($uid, $handle);
            $st['phase'] = $open['phase'];
            $st['log'] = $open['lines'];
            $st['prompt'] = $open['prompt'] ?? ($open['phase'] === 'archetype' ? 'pick 1-3' : '>');
            if (isset($open['player_id'])) {
                $st['pid'] = (int) $open['player_id'];
            }
            return $this->screen($st, Mud::takeSfx());
        }

        // plain redraw (client reconnect / post-redirect)
        if ($cmd === 'render' && $line === '' && $key === '') {
            return $this->screen($st, []);
        }

        // ---- archetype selection ------------------------------------
        if ($st['phase'] === 'archetype') {
            $choice = $line !== '' ? $line : ($key !== '' ? $key : '');
            if ($choice === '') {
                return $this->screen($st, []);
            }
            $res = Mud::chooseArchetype($uid, $handle, $choice);
            $this->append($st, $res['lines']);
            if (!empty($res['done'])) {
                $st['phase'] = 'play';
                $st['pid'] = (int) $res['player_id'];
                $st['prompt'] = $res['prompt'] ?? '>';
                $sfx[] = 'levelup';
            }
            return $this->screen($st, array_merge($sfx, Mud::takeSfx()));
        }

        // ---- normal play -----------------------------------------
        $pid = (int) ($st['pid'] ?? 0);
        if ($pid <= 0) {
            unset($st['phase']);
            return $this->run($e, $slug, ['cmd' => 'render'], $st);
        }

        // Only act on a real submission (ENTER in line mode) - not on stray
        // key events. An empty line IS a valid command (advance combat / look).
        $submitted = $cmd === 'submit' || $key === 'ENTER' || $key === "\r" || $line !== '';
        if (!$submitted) {
            return $this->screen($st, []);
        }

        if ($line !== '') {
            $this->append($st, ['|08> |07' . $line]);
        }
        Mud::takeSfx();
        $out = Mud::command($pid, $line);
        $this->append($st, $out);
        $sfx = Mud::takeSfx();

        $p = Player::byId($pid);
        if ($p) {
            $st['prompt'] = Mud::prompt($p);
            $st['room'] = (int) $p['room_id'];
        }
        return $this->screen($st, $sfx);
    }

    /** Append lines to the rolling log, trimming to the cap. */
    private function append(array &$st, array $lines): void
    {
        $st['log'] = array_merge($st['log'] ?? [], $lines);
        if (count($st['log']) > self::LOG_CAP) {
            $st['log'] = array_slice($st['log'], -self::LOG_CAP);
        }
    }

    /** @param list<string> $sfx one-shot effect names to play this frame */
    private function screen(array $st, array $sfx): Frame
    {
        $rows = Frame::pageSize(6);
        $log = $st['log'] ?? [];
        $view = array_slice($log, -$rows);

        $f = Frame::make('screen')->view('game')->title('Hackers-MUD')->mode('line')
            ->header('Hackers-MUD', 'Night City');
        foreach ($view as $l) {
            $f->pipe($l === '' ? ' ' : $l);
        }

        $ambient = 'room';
        if (($st['phase'] ?? '') === 'play' && !empty($st['pid'])) {
            $room = (int) ($st['room'] ?? 0);
            if (!$room) {
                $p = Player::byId((int) $st['pid']);
                $room = $p ? (int) $p['room_id'] : 0;
            }
            if ($room) {
                $ambient = Mud::ambientFor($room);
            }
        }

        $status = preg_replace('/\|\d\d/', '', (string) ($st['prompt'] ?? '>'));
        $status = trim($status) ?: '>';
        if (mb_strlen($status) > 72) {
            $status = mb_substr($status, 0, 72);
        }
        return $f->prompt($status)
            ->meta(['sfx' => array_values(array_slice($sfx, 0, 6)), 'ambient' => $ambient])
            ->footer('type a command · ESC leaves the MUD · "help" for commands');
    }
}
