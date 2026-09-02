<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;
use Bbs\Core\Db;

/**
 * The Game Room. game.list picks a door; play happens in-module with a small
 * per-game state bag ($st['g']). game.scores shows the hall of fame.
 */
final class GamesModule extends Module
{
    public static function slugs(): array
    {
        return ['game.list', 'game.scores'];
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        $key = strtoupper((string) ($in['key'] ?? ''));
        $cmd = (string) ($in['cmd'] ?? '');

        if ($slug === 'game.scores') {
            if ($key === "\x1B" || $key === 'Q') {
                return $e->exitModule();
            }
            return $this->scores($e);
        }

        // deep-link "play this game"
        if (isset($st['play']) && $st['play'] !== '') {
            $g = Db::one('SELECT * FROM games WHERE slug = ? AND enabled = 1', [$st['play']]);
            unset($st['play']);
            if ($g) {
                $st['screen'] = 'play';
                $st['module'] = $g['module'];
                $st['game_id'] = (int) $g['id'];
                $st['g'] = [];
            }
        }

        $screen = $st['screen'] ?? 'list';

        if ($screen === 'play') {
            if ($key === "\x1B") {
                $st['screen'] = 'list';
                return $this->listGames($e);
            }
            $g = $st['g'] ?? [];
            $frame = match ($st['module'] ?? '') {
                'guess'     => $this->guess($e, $in, $g),
                'hangman'   => $this->hangman($e, $in, $g),
                'dice'      => $this->dice($e, $in, $g),
                'blackjack' => $this->blackjack($e, $in, $g),
                'wumpus'    => $this->wumpus($e, $in, $g),
                'lorc'      => $this->lorc($e, $in, $g),
                default     => $this->listGames($e),
            };
            $st['g'] = $g;
            return $frame;
        }

        // list
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }
        $games = Db::all('SELECT * FROM games WHERE enabled = 1 ORDER BY sort, id');
        if (ctype_digit($key) && $key !== '0') {
            $idx = (int) $key - 1;
            if (isset($games[$idx])) {
                Db::q('UPDATE games SET plays = plays + 1 WHERE id = ?', [$games[$idx]['id']]);
                $st['screen'] = 'play';
                $st['module'] = $games[$idx]['module'];
                $st['game_id'] = (int) $games[$idx]['id'];
                $st['g'] = [];
                return $this->run($e, $slug, ['cmd' => 'render'], $st);
            }
        }
        return $this->listGames($e);
    }

    private function listGames(Engine $e): Frame
    {
        $games = Db::all('SELECT * FROM games WHERE enabled = 1 ORDER BY sort, id');
        $f = Frame::make('screen')->title('Game Room')->mode('menu')->header('Door Games')->blank();
        foreach ($games as $i => $g) {
            $f->pipe(sprintf('|08   [|15%2d|08] |14%-28s |07%s', $i + 1, $g['name'], $g['description']));
            $f->pipe(sprintf('        |08%s · played %d times', $g['score_label'], (int) $g['plays']));
        }
        return $f->footer('number to play · Q back');
    }

    private function scores(Engine $e): Frame
    {
        $games = Db::all('SELECT * FROM games WHERE enabled = 1 ORDER BY sort');
        $f = Frame::make('screen')->title('High Scores')->mode('menu')->header('Hall of Fame')->blank();
        foreach ($games as $g) {
            $order = $g['score_order'] === 'asc' ? 'ASC' : 'DESC';
            $rows = Db::all("SELECT handle, score, created_at FROM game_scores WHERE game_id = ? ORDER BY score $order LIMIT 5", [$g['id']]);
            $f->pipe('|14   ' . $g['name'] . ' |08(' . $g['score_label'] . ')');
            foreach ($rows as $i => $r) {
                $f->pipe(sprintf('|07     %d. %-18s |15%s', $i + 1, $r['handle'] ?: 'anon', $r['score']));
            }
            if (!$rows) {
                $f->pipe('|08     no scores yet - be the first');
            }
            $f->blank();
        }
        return $f->footer('Q back');
    }

    private function saveScore(Engine $e, array &$g, int $score, string $meta = ''): void
    {
        if (isset($g['_saved'])) {
            return;
        }
        $gid = 0;
        // game_id is in module state on the node, re-derive from module name
        $row = Db::one('SELECT id FROM games WHERE module = ? LIMIT 1', [$g['_module'] ?? '']);
        $gid = $row['id'] ?? 0;
        if (!$gid) {
            return;
        }
        Db::insert('game_scores', [
            'game_id' => $gid, 'user_id' => $e->session->userId, 'handle' => $e->session->handle(),
            'score' => $score, 'meta' => mb_substr($meta, 0, 250), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $g['_saved'] = true;
    }

    // ===============================================================
    //  GUESS THE NUMBER
    // ===============================================================
    private function guess(Engine $e, array $in, array &$g): Frame
    {
        $g['_module'] = 'guess';
        if (!isset($g['secret'])) {
            $g['secret'] = random_int(1, 1000);
            $g['tries'] = 0;
            $g['hist'] = [];
            $g['msg'] = 'I am thinking of a number from 1 to 1000.';
        }
        $input = trim((string) ($in['input'] ?? ''));
        if ($input !== '' && ctype_digit($input) && !isset($g['won'])) {
            $n = (int) $input;
            $g['tries']++;
            if ($n === $g['secret']) {
                $g['won'] = true;
                $g['msg'] = "GOT IT in {$g['tries']} tries! The number was {$g['secret']}.";
                $this->saveScore($e, $g, $g['tries'], 'tries');
            } elseif ($n < $g['secret']) {
                $g['msg'] = "$n is too LOW.";
                $g['hist'][] = "$n  low";
            } else {
                $g['msg'] = "$n is too HIGH.";
                $g['hist'][] = "$n  high";
            }
        }
        if (isset($g['won']) && strtoupper((string) ($in['key'] ?? '')) === 'ENTER') {
            $g = ['_module' => 'guess'];
            return $this->guess($e, [], $g);
        }

        $f = Frame::make('screen')->view('game')->title('Guess The Number')->mode('game')
            ->header('Guess The Number')->blank()
            ->pipe('|15   ' . $g['msg'])->blank();
        foreach (array_slice($g['hist'], -12) as $h) {
            $f->pipe('|08     ' . $h);
        }
        $f->blank();
        if (isset($g['won'])) {
            $f->pipe('|10   Press ENTER to play again, ESC to leave.')->mode('game');
        } else {
            $f->pipe('|07   Your guess: type a number and press ENTER.')->prompt('Guess')->mode('line');
        }
        return $f->footer('ESC leave');
    }

    // ===============================================================
    //  HANGMAN
    // ===============================================================
    private function hangman(Engine $e, array $in, array &$g): Frame
    {
        $g['_module'] = 'hangman';
        $words = ['MODEM', 'CARRIER', 'BAUD', 'ANSI', 'PHREAK', 'TERMINAL', 'SYSOP', 'DIALUP', 'PACKET', 'CONSOLE', 'FIDONET', 'HANDLE', 'DOWNLOAD', 'BULLETIN'];
        if (!isset($g['word'])) {
            $g['word'] = $words[array_rand($words)];
            $g['guessed'] = [];
            $g['bad'] = 0;
            $g['wins'] = (int) ($g['wins'] ?? 0);
        }
        $key = strtoupper((string) ($in['key'] ?? ''));
        $done = isset($g['result']);
        if (!$done && strlen($key) === 1 && ctype_alpha($key) && !in_array($key, $g['guessed'], true)) {
            $g['guessed'][] = $key;
            if (!str_contains($g['word'], $key)) {
                $g['bad']++;
            }
        }
        $revealed = implode(' ', array_map(fn ($c) => in_array($c, $g['guessed'], true) ? $c : '_', str_split($g['word'])));
        if (!$done && !str_contains($revealed, '_')) {
            $g['result'] = 'win';
            $g['wins']++;
            $this->saveScore($e, $g, $g['wins'], 'wins');
        } elseif (!$done && $g['bad'] >= 6) {
            $g['result'] = 'lose';
        }
        if (isset($g['result']) && $key === 'ENTER') {
            $wins = $g['wins'];
            $g = ['_module' => 'hangman', 'wins' => $wins, '_saved' => $g['_saved'] ?? false];
            return $this->hangman($e, [], $g);
        }

        $gallows = [
            "  +---+  \n      |  \n      |  \n      |  \n     ===",
            "  +---+  \n  O   |  \n      |  \n      |  \n     ===",
            "  +---+  \n  O   |  \n  |   |  \n      |  \n     ===",
            "  +---+  \n  O   |  \n /|   |  \n      |  \n     ===",
            "  +---+  \n  O   |  \n /|\\  |  \n      |  \n     ===",
            "  +---+  \n  O   |  \n /|\\  |  \n /    |  \n     ===",
            "  +---+  \n  O   |  \n /|\\  |  \n / \\  |  \n     ===",
        ];
        $f = Frame::make('screen')->view('game')->title('Hangman')->mode('game')
            ->header('Hangman', 'wins: ' . $g['wins'])->blank();
        foreach (explode("\n", $gallows[min(6, $g['bad'])]) as $l) {
            $f->pipe('|12   ' . $l);
        }
        $f->blank()->pipe('|15   ' . $revealed)->blank()
          ->pipe('|08   Tried: ' . implode(' ', $g['guessed']));
        if (($g['result'] ?? '') === 'win') {
            $f->blank()->pipe('|10   You saved him! ENTER for another, ESC to leave.');
        } elseif (($g['result'] ?? '') === 'lose') {
            $f->blank()->pipe('|12   He is done for. The word was ' . $g['word'] . '. ENTER retry, ESC leave.');
        } else {
            $f->blank()->pipe('|07   Press a letter.');
        }
        return $f->footer('A-Z guess · ESC leave');
    }

    // ===============================================================
    //  TEN THOUSAND (push-your-luck dice)
    // ===============================================================
    private function dice(Engine $e, array $in, array &$g): Frame
    {
        $g['_module'] = 'dice';
        $g['total'] ??= 0;
        $g['turn'] ??= 0;
        $g['dice'] ??= [];
        $g['msg'] ??= 'Press R to roll 6 dice. Score 1s (100) and 5s (50). Bank before you bust!';
        $key = strtoupper((string) ($in['key'] ?? ''));

        if ($key === 'R') {
            $roll = [];
            for ($i = 0; $i < 6; $i++) {
                $roll[] = random_int(1, 6);
            }
            $g['dice'] = $roll;
            $gain = 0;
            $ones = count(array_filter($roll, fn ($d) => $d === 1));
            $fives = count(array_filter($roll, fn ($d) => $d === 5));
            $gain += $ones * 100 + $fives * 50;
            foreach ([2, 3, 4, 6] as $face) {
                if (count(array_filter($roll, fn ($d) => $d === $face)) >= 3) {
                    $gain += $face * 100;
                }
            }
            if (count(array_unique($roll)) === 6) {
                $gain = 1500;
            }
            if ($gain === 0) {
                $g['msg'] = 'BUST! Turn score lost.';
                $g['turn'] = 0;
            } else {
                $g['turn'] += $gain;
                $g['msg'] = "Rolled +$gain. Turn: {$g['turn']}. R roll again, B bank.";
            }
        } elseif ($key === 'B' && $g['turn'] > 0) {
            $g['total'] += $g['turn'];
            $g['msg'] = "Banked {$g['turn']}. Total: {$g['total']}.";
            $g['turn'] = 0;
            if ($g['total'] >= 10000) {
                $g['msg'] = "YOU WIN with {$g['total']}!";
                $this->saveScore($e, $g, $g['total'], 'ten-thousand');
            }
        }

        $faces = ['', '⚀', '⚁', '⚂', '⚃', '⚄', '⚅'];
        $f = Frame::make('screen')->view('game')->title('Ten Thousand')->mode('game')
            ->header('Ten Thousand', 'total: ' . $g['total'])->blank()
            ->pipe('|15   ' . $g['msg'])->blank();
        if ($g['dice']) {
            $f->pipe('|14   ' . implode('  ', array_map(fn ($d) => $faces[$d] . $d, $g['dice'])));
        }
        $f->blank()->pipe('|07   Turn score: |14' . $g['turn'] . '|07     Banked: |14' . $g['total']);
        return $f->footer('R roll · B bank · ESC leave');
    }

    // ===============================================================
    //  ONE-ARMED BANDIT (simple blackjack)
    // ===============================================================
    private function blackjack(Engine $e, array $in, array &$g): Frame
    {
        $g['_module'] = 'blackjack';
        $g['chips'] ??= 100;
        $deal = function () {
            return random_int(1, 13);
        };
        $val = function (array $hand) {
            $t = 0;
            $aces = 0;
            foreach ($hand as $c) {
                if ($c === 1) {
                    $aces++;
                    $t += 11;
                } else {
                    $t += min(10, $c);
                }
            }
            while ($t > 21 && $aces > 0) {
                $t -= 10;
                $aces--;
            }
            return $t;
        };
        $key = strtoupper((string) ($in['key'] ?? ''));

        if (!isset($g['player']) || ($g['done'] ?? false) && $key === 'ENTER') {
            $g['player'] = [$deal(), $deal()];
            $g['dealer'] = [$deal(), $deal()];
            $g['done'] = false;
            $g['msg'] = 'H hit, S stand. Bet is 10 chips.';
        }
        if (!$g['done'] && $key === 'H') {
            $g['player'][] = $deal();
            if ($val($g['player']) > 21) {
                $g['done'] = true;
                $g['chips'] -= 10;
                $g['msg'] = 'BUST. -10 chips.';
            }
        } elseif (!$g['done'] && $key === 'S') {
            while ($val($g['dealer']) < 17) {
                $g['dealer'][] = $deal();
            }
            $p = $val($g['player']);
            $d = $val($g['dealer']);
            if ($d > 21 || $p > $d) {
                $g['chips'] += 10;
                $g['msg'] = 'YOU WIN. +10 chips.';
            } elseif ($p === $d) {
                $g['msg'] = 'Push.';
            } else {
                $g['chips'] -= 10;
                $g['msg'] = 'Dealer wins. -10 chips.';
            }
            $g['done'] = true;
            $this->saveScore($e, $g, $g['chips'], 'chips');
        }

        $show = fn (array $h) => implode(' ', array_map(fn ($c) => $c === 1 ? 'A' : ($c === 11 ? 'J' : ($c === 12 ? 'Q' : ($c === 13 ? 'K' : (string) $c))), $h));
        $f = Frame::make('screen')->view('game')->title('One-Armed Bandit')->mode('game')
            ->header('Blackjack', 'chips: ' . $g['chips'])->blank()
            ->pipe('|08   Dealer: |14' . ($g['done'] ? $show($g['dealer']) . ' (' . $val($g['dealer']) . ')' : $show([$g['dealer'][0]]) . ' ??'))
            ->pipe('|08   You   : |15' . $show($g['player']) . ' (' . $val($g['player']) . ')')
            ->blank()->pipe('|15   ' . $g['msg']);
        if ($g['done']) {
            $f->blank()->pipe('|10   ENTER for a new hand, ESC to leave.');
        }
        return $f->footer('H hit · S stand · ESC leave');
    }

    // ===============================================================
    //  HUNT THE WUMPUS (compact)
    // ===============================================================
    private function wumpus(Engine $e, array $in, array &$g): Frame
    {
        $g['_module'] = 'wumpus';
        $map = [1 => [2, 5, 8], 2 => [1, 3, 10], 3 => [2, 4, 12], 4 => [3, 5, 14], 5 => [1, 4, 6],
                6 => [5, 7, 15], 7 => [6, 8, 17], 8 => [1, 7, 9], 9 => [8, 10, 18], 10 => [2, 9, 11],
                11 => [10, 12, 19], 12 => [3, 11, 13], 13 => [12, 14, 20], 14 => [4, 13, 15], 15 => [6, 14, 16],
                16 => [15, 17, 20], 17 => [7, 16, 18], 18 => [9, 17, 19], 19 => [11, 18, 20], 20 => [13, 16, 19]];
        if (!isset($g['room'])) {
            $spots = array_rand(array_flip(range(1, 20)), 4);
            $g['room'] = (int) $spots[0];
            $g['wumpus'] = (int) $spots[1];
            $g['pit'] = (int) $spots[2];
            $g['bat'] = (int) $spots[3];
            $g['moves'] = 0;
            $g['msg'] = 'You are in the caves. Find and shoot the Wumpus.';
        }
        $key = strtoupper((string) ($in['key'] ?? ''));
        $input = trim((string) ($in['input'] ?? ''));
        $done = isset($g['end']);

        if (!$done && $input !== '') {
            if (preg_match('/^S\s*(\d+)/i', $input, $m)) {
                $t = (int) $m[1];
                if ($t === $g['wumpus']) {
                    $g['end'] = 'win';
                    $g['msg'] = 'You shot the Wumpus! Victory in ' . $g['moves'] . ' moves.';
                    $this->saveScore($e, $g, $g['moves'], 'moves');
                } else {
                    $g['end'] = 'lose';
                    $g['msg'] = 'Your arrow missed. The Wumpus heard you and ate you.';
                }
            } elseif (ctype_digit($input)) {
                $t = (int) $input;
                if (in_array($t, $map[$g['room']] ?? [], true)) {
                    $g['room'] = $t;
                    $g['moves']++;
                    if ($t === $g['wumpus']) {
                        $g['end'] = 'lose';
                        $g['msg'] = 'You walked into the Wumpus. It was not pleased.';
                    } elseif ($t === $g['pit']) {
                        $g['end'] = 'lose';
                        $g['msg'] = 'You fell into a bottomless pit.';
                    } elseif ($t === $g['bat']) {
                        $g['room'] = random_int(1, 20);
                        $g['msg'] = 'Giant bats grabbed you and dropped you somewhere else!';
                    } else {
                        $g['msg'] = 'You move to room ' . $t . '.';
                    }
                } else {
                    $g['msg'] = 'No tunnel to room ' . $t . '.';
                }
            }
        }
        if ($done && $key === 'ENTER') {
            $g = ['_module' => 'wumpus', '_saved' => $g['_saved'] ?? false];
            return $this->wumpus($e, [], $g);
        }

        $adj = $map[$g['room']] ?? [];
        $warn = [];
        if (in_array($g['wumpus'], $adj, true)) {
            $warn[] = '|12You smell the Wumpus.';
        }
        if (in_array($g['pit'], $adj, true)) {
            $warn[] = '|11You feel a draft.';
        }
        if (in_array($g['bat'], $adj, true)) {
            $warn[] = '|13You hear wings.';
        }
        $f = Frame::make('screen')->view('game')->title('Hunt The Wumpus')->mode('line')
            ->header('Hunt The Wumpus', 'moves: ' . $g['moves'])->blank()
            ->pipe('|15   ' . $g['msg'])->blank()
            ->pipe('|07   Room |14' . $g['room'] . '|07   tunnels to |14' . implode(', ', $adj));
        foreach ($warn as $w) {
            $f->pipe('   ' . $w);
        }
        $f->blank();
        if ($done) {
            $f->pipe('|10   ENTER to crawl back in, ESC to leave.')->mode('game');
        } else {
            $f->pipe('|07   Enter a room number to move, or "S <room>" to shoot.')->prompt('Cave');
        }
        return $f->footer('ESC leave');
    }

    // ===============================================================
    //  LEGEND OF THE RED CONSOLE (tiny LORD-style)
    // ===============================================================
    private function lorc(Engine $e, array $in, array &$g): Frame
    {
        $g['_module'] = 'lorc';
        $g['level'] ??= 1;
        $g['hp'] ??= 20;
        $g['maxhp'] ??= 20;
        $g['gold'] ??= 0;
        $g['xp'] ??= 0;
        $g['msg'] ??= 'The forest is dark. Fight monsters, gain levels, seek the Red Dragon.';
        $key = strtoupper((string) ($in['key'] ?? ''));

        $monsters = ['a Bit Gremlin', 'a Null Pointer', 'the Packet Wraith', 'a Segfault Slime', 'the Carrier Ghoul', 'a Baud Bat'];
        if ($key === 'F' && $g['hp'] > 0) {
            $mon = $monsters[array_rand($monsters)];
            $foeHp = 6 + $g['level'] * 4 + random_int(0, 6);
            $you = random_int(3, 6) + $g['level'] * 2;
            $foe = random_int(1, 4) + $g['level'];
            if ($you >= $foeHp) {
                $reward = 5 + $g['level'] * 3;
                $g['gold'] += $reward;
                $g['xp'] += $reward;
                $g['msg'] = "You strike down $mon. +$reward gold/xp.";
                if ($g['xp'] >= $g['level'] * 30) {
                    $g['level']++;
                    $g['maxhp'] += 8;
                    $g['hp'] = $g['maxhp'];
                    $g['msg'] .= "  ** LEVEL UP to {$g['level']}! **";
                    $this->saveScore($e, $g, $g['level'], 'gold ' . $g['gold']);
                }
            } else {
                $g['hp'] -= $foe;
                $g['msg'] = "You wound $mon but take $foe damage.";
                if ($g['hp'] <= 0) {
                    $g['hp'] = 0;
                    $g['msg'] = "$mon lays you low. You wake at the inn, wiser.";
                }
            }
        } elseif ($key === 'H') {
            $cost = $g['level'] * 5;
            if ($g['gold'] >= $cost) {
                $g['gold'] -= $cost;
                $g['hp'] = $g['maxhp'];
                $g['msg'] = "The healer patches you up for $cost gold.";
            } else {
                $g['msg'] = "The healer wants $cost gold you do not have.";
            }
        } elseif ($key === 'R' && $g['hp'] <= 0) {
            $g['hp'] = $g['maxhp'];
            $g['msg'] = 'You return to the forest path.';
        }

        $bar = str_repeat('█', (int) round(20 * $g['hp'] / max(1, $g['maxhp'])));
        $f = Frame::make('screen')->view('game')->title('Legend of the Red Console')->mode('game')
            ->header('Legend of the Red Console', 'Lv ' . $g['level'])->blank()
            ->pipe('|15   ' . $g['msg'])->blank()
            ->pipe(sprintf('|07   HP |12%-20s|07 %d/%d', $bar, $g['hp'], $g['maxhp']))
            ->pipe(sprintf('|07   Gold |14%d|07   XP |14%d|07 / %d', $g['gold'], $g['xp'], $g['level'] * 30));
        if ($g['hp'] <= 0) {
            $f->blank()->pipe('|11   R to rest and return, ESC to leave.');
        } else {
            $f->blank()->pipe('|07   F fight · H visit healer · ESC leave');
        }
        return $f->footer('ESC leave');
    }
}
