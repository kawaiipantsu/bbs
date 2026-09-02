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
                'rps'       => $this->rps($e, $in, $g),
                'ttt'       => $this->ttt($e, $in, $g),
                'nim'       => $this->nim($e, $in, $g),
                'mastermind' => $this->mastermind($e, $in, $g),
                'anagram'   => $this->anagram($e, $in, $g),
                'hilo'      => $this->hilo($e, $in, $g),
                'craps'     => $this->craps($e, $in, $g),
                'mines'     => $this->mines($e, $in, $g),
                'g2048'     => $this->g2048($e, $in, $g),
                'lightsout' => $this->lightsout($e, $in, $g),
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
        $idx = self::keyToIndex($key);
        if ($idx !== null && isset($games[$idx])) {
            Db::q('UPDATE games SET plays = plays + 1 WHERE id = ?', [$games[$idx]['id']]);
            $st['screen'] = 'play';
            $st['module'] = $games[$idx]['module'];
            $st['game_id'] = (int) $games[$idx]['id'];
            $st['g'] = [];
            return $this->run($e, $slug, ['cmd' => 'render'], $st);
        }
        return $this->listGames($e);
    }

    /** 1..9 then A,B,C... -> 0-based game index */
    private static function keyToIndex(string $key): ?int
    {
        if ($key === '') {
            return null;
        }
        if (ctype_digit($key) && $key !== '0') {
            return (int) $key - 1;
        }
        if (strlen($key) === 1 && ctype_alpha($key)) {
            return 9 + (ord(strtoupper($key)) - 65);
        }
        return null;
    }

    private static function indexLabel(int $i): string
    {
        return $i < 9 ? (string) ($i + 1) : chr(65 + $i - 9);
    }

    private function listGames(Engine $e): Frame
    {
        $games = Db::all('SELECT * FROM games WHERE enabled = 1 ORDER BY sort, id');
        $f = Frame::make('screen')->title('Game Room')->mode('menu')->header('Door Games', count($games) . ' games')->blank();
        $col = 0;
        foreach ($games as $i => $g) {
            $lbl = self::indexLabel($i);
            $f->pipe(sprintf('|08 [|15%2s|08] |14%-26s |08%s', $lbl, mb_substr($g['name'], 0, 26), mb_substr($g['description'], 0, 62)));
        }
        return $f->footer('press the letter / number to play · Q back');
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

    // ===============================================================
    //  ROCK PAPER SCISSORS  (first to 5)
    // ===============================================================
    private function rps(Engine $e, array $in, array &$g): Frame
    {
        $g['_module'] = 'rps';
        $g['you'] ??= 0; $g['cpu'] ??= 0; $g['msg'] ??= 'First to 5. Press R, P or S.';
        $names = ['R' => 'Rock', 'P' => 'Paper', 'S' => 'Scissors'];
        $beats = ['R' => 'S', 'P' => 'R', 'S' => 'P'];
        $key = strtoupper((string) ($in['key'] ?? ''));
        $done = $g['you'] >= 5 || $g['cpu'] >= 5;

        if (!$done && isset($names[$key])) {
            $c = ['R', 'P', 'S'][random_int(0, 2)];
            if ($key === $c) {
                $g['msg'] = "Both chose {$names[$key]}. Draw.";
            } elseif ($beats[$key] === $c) {
                $g['you']++;
                $g['msg'] = "{$names[$key]} beats {$names[$c]}. You score.";
            } else {
                $g['cpu']++;
                $g['msg'] = "{$names[$c]} beats {$names[$key]}. CPU scores.";
            }
            if ($g['you'] >= 5) {
                $g['msg'] = 'You win the match 5-' . $g['cpu'] . '!';
                $this->saveScore($e, $g, $g['you'] - $g['cpu'], 'won 5-' . $g['cpu']);
            } elseif ($g['cpu'] >= 5) {
                $g['msg'] = 'CPU wins the match ' . $g['you'] . '-5.';
            }
        }
        if (($g['you'] >= 5 || $g['cpu'] >= 5) && $key === 'ENTER') {
            $g = ['_module' => 'rps', '_saved' => $g['_saved'] ?? false];
            return $this->rps($e, [], $g);
        }

        $f = Frame::make('screen')->view('game')->title('Rock Paper Scissors')->mode('game')
            ->header('Rock Paper Scissors', $g['you'] . ' - ' . $g['cpu'])->blank()
            ->pipe('|15   ' . $g['msg'])->blank()
            ->pipe('|07   You |14' . str_repeat('#', $g['you']) . '|08' . str_repeat('.', 5 - $g['you']))
            ->pipe('|07   CPU |12' . str_repeat('#', $g['cpu']) . '|08' . str_repeat('.', 5 - $g['cpu']));
        if ($g['you'] >= 5 || $g['cpu'] >= 5) {
            $f->blank()->pipe('|10   ENTER for a rematch, ESC to leave.');
        }
        return $f->footer('R rock · P paper · S scissors · ESC leave');
    }

    // ===============================================================
    //  TIC-TAC-TOE  (you are X, CPU blocks/wins)
    // ===============================================================
    private function ttt(Engine $e, array $in, array &$g): Frame
    {
        $g['_module'] = 'ttt';
        $g['b'] ??= array_fill(0, 9, ' ');
        $g['msg'] ??= 'You are X. Press 1-9 for a square.';
        $key = strtoupper((string) ($in['key'] ?? ''));
        $b = &$g['b'];
        $win = static function (array $b, string $p): bool {
            $L = [[0,1,2],[3,4,5],[6,7,8],[0,3,6],[1,4,7],[2,5,8],[0,4,8],[2,4,6]];
            foreach ($L as $l) {
                if ($b[$l[0]] === $p && $b[$l[1]] === $p && $b[$l[2]] === $p) {
                    return true;
                }
            }
            return false;
        };
        $over = $win($b, 'X') || $win($b, 'O') || !in_array(' ', $b, true);

        if (!$over && ctype_digit($key) && $key !== '0') {
            $i = (int) $key - 1;
            if ($b[$i] === ' ') {
                $b[$i] = 'X';
                if (!$win($b, 'X') && in_array(' ', $b, true)) {
                    // CPU: win, else block, else centre, else random
                    $pick = null;
                    foreach (['O', 'X'] as $p) {
                        for ($j = 0; $j < 9 && $pick === null; $j++) {
                            if ($b[$j] === ' ') {
                                $b[$j] = $p;
                                if ($win($b, $p)) {
                                    $pick = $j;
                                }
                                $b[$j] = ' ';
                            }
                        }
                    }
                    if ($pick === null && $b[4] === ' ') {
                        $pick = 4;
                    }
                    if ($pick === null) {
                        $free = array_keys($b, ' ', true);
                        $pick = $free[array_rand($free)];
                    }
                    $b[$pick] = 'O';
                }
                $g['msg'] = $win($b, 'X') ? 'You win!' : ($win($b, 'O') ? 'CPU wins.' : (in_array(' ', $b, true) ? 'Your move.' : 'A draw.'));
                if ($win($b, 'X')) {
                    $this->saveScore($e, $g, 1, 'win');
                }
            }
        }
        if (($win($b, 'X') || $win($b, 'O') || !in_array(' ', $b, true)) && $key === 'ENTER') {
            $g = ['_module' => 'ttt', '_saved' => $g['_saved'] ?? false];
            return $this->ttt($e, [], $g);
        }

        $f = Frame::make('screen')->view('game')->title('Tic-Tac-Toe')->mode('game')
            ->header('Tic-Tac-Toe')->blank()->pipe('|15   ' . $g['msg'])->blank();
        for ($r = 0; $r < 3; $r++) {
            $cells = [];
            for ($c = 0; $c < 3; $c++) {
                $i = $r * 3 + $c;
                $cells[] = $b[$i] === ' ' ? '|08' . ($i + 1) : ($b[$i] === 'X' ? '|14X' : '|12O');
            }
            $f->pipe('     ' . implode('|08 │ ', $cells));
            if ($r < 2) {
                $f->pipe('|08    ───┼───┼───');
            }
        }
        if ($win($b, 'X') || $win($b, 'O') || !in_array(' ', $b, true)) {
            $f->blank()->pipe('|10   ENTER for another game, ESC to leave.');
        }
        return $f->footer('1-9 place · ESC leave');
    }

    // ===============================================================
    //  21 MATCHSTICKS (Nim) - take 1-3, whoever takes the last loses
    // ===============================================================
    private function nim(Engine $e, array $in, array &$g): Frame
    {
        $g['_module'] = 'nim';
        $g['n'] ??= 21;
        $g['msg'] ??= '21 matches. Take 1, 2 or 3. Do NOT take the last one.';
        $key = strtoupper((string) ($in['key'] ?? ''));
        $done = isset($g['end']);

        if (!$done && in_array($key, ['1', '2', '3'], true)) {
            $take = min((int) $key, $g['n']);
            $g['n'] -= $take;
            if ($g['n'] <= 0) {
                $g['end'] = 'lose';
                $g['msg'] = 'You took the last match. You lose!';
            } else {
                // CPU plays to leave (multiple of 4) + 1
                $cpu = ($g['n'] - 1) % 4;
                if ($cpu === 0) {
                    $cpu = random_int(1, min(3, $g['n'] - 1));
                }
                $cpu = min($cpu, $g['n']);
                $g['n'] -= $cpu;
                if ($g['n'] <= 0) {
                    $g['end'] = 'win';
                    $g['msg'] = "CPU took $cpu and grabbed the last match. You win!";
                    $this->saveScore($e, $g, 1, 'win');
                } else {
                    $g['msg'] = "You took $take, CPU took $cpu. " . $g['n'] . ' left.';
                }
            }
        }
        if ($done && $key === 'ENTER') {
            $g = ['_module' => 'nim', '_saved' => $g['_saved'] ?? false];
            return $this->nim($e, [], $g);
        }

        $rows = str_repeat('|', max(0, $g['n']));
        $f = Frame::make('screen')->view('game')->title('21 Matchsticks')->mode('game')
            ->header('21 Matchsticks', $g['n'] . ' left')->blank()
            ->pipe('|15   ' . $g['msg'])->blank()
            ->pipe('|11   ' . implode(' ', str_split($rows)))
            ->blank();
        $f->pipe($done ? '|10   ENTER to play again, ESC to leave.' : '|07   Take 1, 2 or 3.');
        return $f->footer('1 / 2 / 3 · ESC leave');
    }

    // ===============================================================
    //  MASTERMIND - crack the 4-digit code (digits 1-6), 10 tries
    // ===============================================================
    private function mastermind(Engine $e, array $in, array &$g): Frame
    {
        $g['_module'] = 'mastermind';
        if (!isset($g['code'])) {
            $g['code'] = '';
            for ($i = 0; $i < 4; $i++) {
                $g['code'] .= random_int(1, 6);
            }
            $g['tries'] = [];
            $g['msg'] = 'Guess the 4-digit code. Digits 1-6. 10 tries.';
        }
        $input = preg_replace('/\D/', '', (string) ($in['input'] ?? ''));
        $key = strtoupper((string) ($in['key'] ?? ''));
        $solved = in_array($g['code'], array_column($g['tries'], 0), true);

        if (!$solved && count($g['tries']) < 10 && strlen($input) === 4 && strspn($input, '123456') === 4) {
            $bulls = 0;
            $cows = 0;
            $cc = str_split($g['code']);
            $gg = str_split($input);
            for ($i = 0; $i < 4; $i++) {
                if ($gg[$i] === $cc[$i]) {
                    $bulls++;
                    $cc[$i] = $gg[$i] = '*';
                }
            }
            for ($i = 0; $i < 4; $i++) {
                if ($gg[$i] !== '*' && ($k = array_search($gg[$i], $cc, true)) !== false) {
                    $cows++;
                    $cc[$k] = '-';
                }
            }
            $g['tries'][] = [$input, $bulls, $cows];
            if ($bulls === 4) {
                $g['msg'] = 'Cracked it in ' . count($g['tries']) . '!';
                $this->saveScore($e, $g, count($g['tries']), 'cracked');
            } elseif (count($g['tries']) >= 10) {
                $g['msg'] = 'Out of tries. The code was ' . $g['code'] . '.';
            } else {
                $g['msg'] = "$bulls exact, $cows misplaced.";
            }
        }
        $ended = in_array($g['code'], array_column($g['tries'], 0), true) || count($g['tries']) >= 10;
        if ($ended && $key === 'ENTER') {
            $g = ['_module' => 'mastermind', '_saved' => $g['_saved'] ?? false];
            return $this->mastermind($e, [], $g);
        }

        $f = Frame::make('screen')->view('game')->title('Mastermind')->mode($ended ? 'game' : 'line')
            ->header('Mastermind', count($g['tries']) . '/10')->blank()
            ->pipe('|15   ' . $g['msg'])->blank();
        foreach ($g['tries'] as $t) {
            $f->pipe(sprintf('|07   %s   |10%s exact  |11%s near', $t[0], $t[1], $t[2]));
        }
        $f->blank();
        if ($ended) {
            $f->pipe('|10   ENTER for a new code, ESC to leave.');
        } else {
            $f->pipe('|07   Type 4 digits (1-6) and press ENTER.')->prompt('Code');
        }
        return $f->footer('ESC leave');
    }

    // ===============================================================
    //  ANAGRAM - unscramble the word
    // ===============================================================
    private function anagram(Engine $e, array $in, array &$g): Frame
    {
        $g['_module'] = 'anagram';
        $words = ['MODEM', 'CARRIER', 'PHOSPHOR', 'TERMINAL', 'PACKET', 'CONSOLE', 'FIDONET', 'DIALTONE',
                  'HANDSHAKE', 'DOWNLOAD', 'SYSOP', 'ANSI', 'BULLETIN', 'BAUDRATE', 'SCROLLBACK', 'KEYBOARD'];
        $g['score'] ??= 0;
        $g['round'] ??= 0;
        if (!isset($g['word'])) {
            $g['word'] = $words[array_rand($words)];
            $chars = str_split($g['word']);
            shuffle($chars);
            $g['scram'] = implode('', $chars);
            $g['round']++;
            $g['msg'] = 'Round ' . $g['round'] . ' - unscramble it.';
        }
        $input = strtoupper(trim((string) ($in['input'] ?? '')));
        $key = strtoupper((string) ($in['key'] ?? ''));

        if ($input !== '') {
            if ($input === $g['word']) {
                $g['score'] += 10 + strlen($g['word']);
                $g['msg'] = 'Yes! +' . (10 + strlen($g['word'])) . '. Next word...';
                unset($g['word']);
                if ($g['round'] >= 8) {
                    $g['end'] = true;
                    $g['msg'] = 'Eight rounds done. Final score ' . $g['score'] . '.';
                    $this->saveScore($e, $g, $g['score'], 'anagram');
                }
                return $this->anagram($e, [], $g);
            }
            $g['msg'] = 'Not quite. Try again (or type PASS).';
            if ($input === 'PASS') {
                $g['msg'] = 'The word was ' . $g['word'] . '. Next...';
                unset($g['word']);
                return $this->anagram($e, [], $g);
            }
        }
        if (isset($g['end']) && $key === 'ENTER') {
            $g = ['_module' => 'anagram', '_saved' => $g['_saved'] ?? false];
            return $this->anagram($e, [], $g);
        }

        $f = Frame::make('screen')->view('game')->title('Anagram')->mode(isset($g['end']) ? 'game' : 'line')
            ->header('Anagram', 'score ' . $g['score'])->blank()
            ->pipe('|15   ' . $g['msg'])->blank();
        if (isset($g['scram'])) {
            $f->pipe('|14   ' . implode(' ', str_split($g['scram'])));
        }
        $f->blank();
        $f->pipe(isset($g['end']) ? '|10   ENTER to play again, ESC to leave.' : '|07   Type your answer (or PASS) and ENTER.');
        return $f->prompt('Word')->footer('ESC leave');
    }

    // ===============================================================
    //  HI-LO - will the next card be higher or lower? build a streak
    // ===============================================================
    private function hilo(Engine $e, array $in, array &$g): Frame
    {
        $g['_module'] = 'hilo';
        $g['card'] ??= random_int(2, 14);
        $g['streak'] ??= 0;
        $g['best'] ??= 0;
        $g['msg'] ??= 'Card is up. Will the next be Higher or Lower?';
        $key = strtoupper((string) ($in['key'] ?? ''));
        $done = isset($g['bust']);
        $name = static fn ($n) => [11 => 'J', 12 => 'Q', 13 => 'K', 14 => 'A'][$n] ?? (string) $n;

        if (!$done && ($key === 'H' || $key === 'L')) {
            $next = random_int(2, 14);
            $hi = $next > $g['card'];
            $lo = $next < $g['card'];
            $ok = ($key === 'H' && $hi) || ($key === 'L' && $lo);
            $push = $next === $g['card'];
            $g['card'] = $next;
            if ($push) {
                $g['msg'] = 'Same rank - push, guess again.';
            } elseif ($ok) {
                $g['streak']++;
                $g['best'] = max($g['best'], $g['streak']);
                $g['msg'] = 'Right! Streak ' . $g['streak'] . '. Cash out with C.';
            } else {
                $g['bust'] = true;
                $g['msg'] = 'Wrong. Streak broken at ' . $g['streak'] . '.';
                $this->saveScore($e, $g, $g['streak'], 'streak');
            }
        } elseif (!$done && $key === 'C' && $g['streak'] > 0) {
            $g['bust'] = true;
            $g['msg'] = 'Cashed out with a streak of ' . $g['streak'] . '.';
            $this->saveScore($e, $g, $g['streak'], 'cashed');
        }
        if ($done && $key === 'ENTER') {
            $g = ['_module' => 'hilo', '_saved' => $g['_saved'] ?? false];
            return $this->hilo($e, [], $g);
        }

        $f = Frame::make('screen')->view('game')->title('Hi-Lo')->mode('game')
            ->header('Hi-Lo', 'streak ' . $g['streak'] . ' · best ' . $g['best'])->blank()
            ->pipe('|15   ' . $g['msg'])->blank()
            ->pipe('|08   ┌─────┐')
            ->pipe('|08   │ |14' . str_pad($name($g['card']), 3) . '|08 │')
            ->pipe('|08   └─────┘')->blank();
        $f->pipe($done ? '|10   ENTER to play again, ESC to leave.' : '|07   H higher · L lower · C cash out');
        return $f->footer('H / L / C · ESC leave');
    }

    // ===============================================================
    //  CRAPS - a bankroll and a pass-line bet
    // ===============================================================
    private function craps(Engine $e, array $in, array &$g): Frame
    {
        $g['_module'] = 'craps';
        $g['bank'] ??= 100;
        $g['point'] ??= 0;
        $g['msg'] ??= 'Bankroll 100. Bet is 10. Press R to roll.';
        $key = strtoupper((string) ($in['key'] ?? ''));

        if ($key === 'R' && $g['bank'] >= 10) {
            $d1 = random_int(1, 6);
            $d2 = random_int(1, 6);
            $sum = $d1 + $d2;
            $roll = "$d1+$d2=$sum";
            if ($g['point'] === 0) {
                if ($sum === 7 || $sum === 11) {
                    $g['bank'] += 10;
                    $g['msg'] = "$roll - a natural! +10.";
                } elseif (in_array($sum, [2, 3, 12], true)) {
                    $g['bank'] -= 10;
                    $g['msg'] = "$roll - craps. -10.";
                } else {
                    $g['point'] = $sum;
                    $g['msg'] = "$roll - point is $sum. Roll it again before a 7.";
                }
            } else {
                if ($sum === $g['point']) {
                    $g['bank'] += 10;
                    $g['msg'] = "$roll - hit the point! +10.";
                    $g['point'] = 0;
                } elseif ($sum === 7) {
                    $g['bank'] -= 10;
                    $g['msg'] = "$roll - seven out. -10.";
                    $g['point'] = 0;
                } else {
                    $g['msg'] = "$roll - no decision. Roll again.";
                }
            }
            if ($g['bank'] <= 0) {
                $g['msg'] = 'Tapped out. Better luck next call.';
                $this->saveScore($e, $g, 0, 'busted');
            } elseif ($g['bank'] >= 200) {
                $g['msg'] = 'Up to ' . $g['bank'] . ' - the pit boss is watching.';
                $this->saveScore($e, $g, $g['bank'], 'craps');
            }
        }

        $f = Frame::make('screen')->view('game')->title('Craps')->mode('game')
            ->header('Craps', 'bank ' . $g['bank'] . ($g['point'] ? ' · point ' . $g['point'] : ''))->blank()
            ->pipe('|15   ' . $g['msg'])->blank()
            ->pipe('|07   Bankroll: |14' . $g['bank']);
        return $f->footer('R roll · ESC leave');
    }

    // ===============================================================
    //  MINESWEEPER - 8x8, 10 mines. "c r" reveal, "f c r" flag
    // ===============================================================
    private function mines(Engine $e, array $in, array &$g): Frame
    {
        $g['_module'] = 'mines';
        $N = 8;
        $M = 10;
        if (!isset($g['mines'])) {
            $spots = (array) array_rand(array_flip(range(0, $N * $N - 1)), $M);
            $g['mines'] = array_fill(0, $N * $N, false);
            foreach ($spots as $s) {
                $g['mines'][$s] = true;
            }
            $g['shown'] = array_fill(0, $N * $N, false);
            $g['flag'] = array_fill(0, $N * $N, false);
            $g['msg'] = '8x8, 10 mines. Type "col row" to dig, "f col row" to flag.';
        }
        $adj = static function (int $i) use ($N) {
            $r = intdiv($i, $N);
            $c = $i % $N;
            $out = [];
            for ($dr = -1; $dr <= 1; $dr++) {
                for ($dc = -1; $dc <= 1; $dc++) {
                    if ($dr || $dc) {
                        $nr = $r + $dr;
                        $nc = $c + $dc;
                        if ($nr >= 0 && $nr < $N && $nc >= 0 && $nc < $N) {
                            $out[] = $nr * $N + $nc;
                        }
                    }
                }
            }
            return $out;
        };
        $count = function (int $i) use ($adj, $g) {
            $n = 0;
            foreach ($adj($i) as $j) {
                if ($g['mines'][$j]) {
                    $n++;
                }
            }
            return $n;
        };
        $input = strtolower(trim((string) ($in['input'] ?? '')));
        $key = strtoupper((string) ($in['key'] ?? ''));
        $done = isset($g['end']);

        if (!$done && $input !== '' && preg_match('/^(f\s+)?(\d+)\s+(\d+)$/', $input, $m)) {
            $c = (int) $m[2];
            $r = (int) $m[3];
            if ($c >= 1 && $c <= $N && $r >= 1 && $r <= $N) {
                $i = ($r - 1) * $N + ($c - 1);
                if ($m[1]) {
                    $g['flag'][$i] = !$g['flag'][$i];
                } elseif (!$g['flag'][$i]) {
                    if ($g['mines'][$i]) {
                        $g['end'] = 'boom';
                        $g['msg'] = 'BOOM. You hit a mine at ' . $c . ',' . $r . '.';
                    } else {
                        // flood fill zeros
                        $stack = [$i];
                        while ($stack) {
                            $x = array_pop($stack);
                            if ($g['shown'][$x]) {
                                continue;
                            }
                            $g['shown'][$x] = true;
                            if ($count($x) === 0) {
                                foreach ($adj($x) as $j) {
                                    if (!$g['shown'][$j] && !$g['mines'][$j]) {
                                        $stack[] = $j;
                                    }
                                }
                            }
                        }
                        $safe = $N * $N - $M;
                        $seen = count(array_filter($g['shown']));
                        if ($seen >= $safe) {
                            $g['end'] = 'clear';
                            $g['msg'] = 'Field cleared! Nice nerves.';
                            $this->saveScore($e, $g, $seen, 'cleared');
                        } else {
                            $g['msg'] = "$seen / $safe safe squares dug.";
                        }
                    }
                }
            }
        }
        if ($done && $key === 'ENTER') {
            $g = ['_module' => 'mines', '_saved' => $g['_saved'] ?? false];
            return $this->mines($e, [], $g);
        }

        $f = Frame::make('screen')->view('game')->title('Minesweeper')->mode($done ? 'game' : 'line')
            ->header('Minesweeper', $done ? strtoupper((string) $g['end']) : '10 mines')->blank()
            ->pipe('|15   ' . $g['msg'])->blank()
            ->pipe('|08      ' . implode(' ', range(1, $N)));
        for ($r = 0; $r < $N; $r++) {
            $line = '|08   ' . ($r + 1) . ' ';
            for ($c = 0; $c < $N; $c++) {
                $i = $r * $N + $c;
                if ($done && $g['mines'][$i]) {
                    $line .= '|12*';
                } elseif ($g['flag'][$i]) {
                    $line .= '|11F';
                } elseif (!$g['shown'][$i]) {
                    $line .= '|08·';
                } else {
                    $n = $count($i);
                    $line .= $n ? '|14' . $n : '|08 ';
                }
                $line .= ' ';
            }
            $f->pipe($line);
        }
        $f->blank()->pipe($done ? '|10   ENTER for a new field, ESC to leave.' : '|07   e.g.  3 4   or   f 3 4');
        return $f->prompt('Dig')->footer('ESC leave');
    }

    // ===============================================================
    //  2048 - arrow keys, merge tiles, reach 2048
    // ===============================================================
    private function g2048(Engine $e, array $in, array &$g): Frame
    {
        $g['_module'] = 'g2048';
        if (!isset($g['grid'])) {
            $g['grid'] = array_fill(0, 16, 0);
            $g['score'] = 0;
            $this->g2048Spawn($g['grid']);
            $this->g2048Spawn($g['grid']);
            $g['msg'] = 'Arrow keys slide the board. Merge to 2048.';
        }
        $key = strtoupper((string) ($in['key'] ?? ''));
        $dir = ['UP' => 0, 'RIGHT' => 1, 'DOWN' => 2, 'LEFT' => 3][$key] ?? null;
        $done = isset($g['end']);

        if (!$done && $dir !== null) {
            $before = $g['grid'];
            $gained = $this->g2048Move($g['grid'], $dir);
            if ($g['grid'] !== $before) {
                $g['score'] += $gained;
                $this->g2048Spawn($g['grid']);
                if (in_array(2048, $g['grid'], true)) {
                    $g['end'] = 'win';
                    $g['msg'] = 'You made 2048! Score ' . $g['score'] . '.';
                    $this->saveScore($e, $g, $g['score'], 'won');
                } elseif (!$this->g2048CanMove($g['grid'])) {
                    $g['end'] = 'lose';
                    $g['msg'] = 'No moves left. Score ' . $g['score'] . '.';
                    $this->saveScore($e, $g, $g['score'], 'stuck');
                } else {
                    $g['msg'] = 'Score ' . $g['score'];
                }
            }
        }
        if ($done && $key === 'ENTER') {
            $g = ['_module' => 'g2048', '_saved' => $g['_saved'] ?? false];
            return $this->g2048($e, [], $g);
        }

        $f = Frame::make('screen')->view('game')->title('2048')->mode('game')
            ->header('2048', 'score ' . $g['score'])->blank()
            ->pipe('|15   ' . $g['msg'])->blank();
        for ($r = 0; $r < 4; $r++) {
            $line = '   ';
            for ($c = 0; $c < 4; $c++) {
                $v = $g['grid'][$r * 4 + $c];
                $col = $v >= 128 ? '|11' : ($v >= 16 ? '|14' : ($v ? '|07' : '|08'));
                $line .= $col . str_pad((string) ($v ?: '.'), 6, ' ', STR_PAD_LEFT);
            }
            $f->pipe($line);
            $f->blank();
        }
        $f->pipe($done ? '|10   ENTER for a new board, ESC to leave.' : '|07   ↑ ↓ ← → to slide');
        return $f->footer('arrows · ESC leave');
    }

    private function g2048Spawn(array &$grid): void
    {
        $free = array_keys($grid, 0, true);
        if ($free) {
            $grid[$free[array_rand($free)]] = random_int(1, 10) === 1 ? 4 : 2;
        }
    }

    private function g2048Move(array &$grid, int $dir): int
    {
        $gained = 0;
        $line = function (int $k) use ($grid, $dir): array {
            $out = [];
            for ($i = 0; $i < 4; $i++) {
                $out[] = match ($dir) {
                    0 => $grid[$i * 4 + $k],
                    2 => $grid[(3 - $i) * 4 + $k],
                    3 => $grid[$k * 4 + $i],
                    default => $grid[$k * 4 + (3 - $i)],
                };
            }
            return $out;
        };
        $put = function (int $k, array $vals) use (&$grid, $dir): void {
            for ($i = 0; $i < 4; $i++) {
                $idx = match ($dir) {
                    0 => $i * 4 + $k,
                    2 => (3 - $i) * 4 + $k,
                    3 => $k * 4 + $i,
                    default => $k * 4 + (3 - $i),
                };
                $grid[$idx] = $vals[$i];
            }
        };
        for ($k = 0; $k < 4; $k++) {
            $vals = array_values(array_filter($line($k)));
            $merged = [];
            for ($i = 0; $i < count($vals); $i++) {
                if ($i + 1 < count($vals) && $vals[$i] === $vals[$i + 1]) {
                    $merged[] = $vals[$i] * 2;
                    $gained += $vals[$i] * 2;
                    $i++;
                } else {
                    $merged[] = $vals[$i];
                }
            }
            while (count($merged) < 4) {
                $merged[] = 0;
            }
            $put($k, $merged);
        }
        return $gained;
    }

    private function g2048CanMove(array $grid): bool
    {
        if (in_array(0, $grid, true)) {
            return true;
        }
        for ($r = 0; $r < 4; $r++) {
            for ($c = 0; $c < 4; $c++) {
                $v = $grid[$r * 4 + $c];
                if ($c < 3 && $v === $grid[$r * 4 + $c + 1]) {
                    return true;
                }
                if ($r < 3 && $v === $grid[($r + 1) * 4 + $c]) {
                    return true;
                }
            }
        }
        return false;
    }

    // ===============================================================
    //  LIGHTS OUT - 5x5, toggle a light and its neighbours, clear the board
    // ===============================================================
    private function lightsout(Engine $e, array $in, array &$g): Frame
    {
        $g['_module'] = 'lightsout';
        $N = 5;
        if (!isset($g['grid'])) {
            $g['grid'] = array_fill(0, $N * $N, false);
            // scramble with random presses so it's always solvable
            for ($p = 0; $p < 8; $p++) {
                $this->loToggle($g['grid'], random_int(0, $N * $N - 1), $N);
            }
            $g['moves'] = 0;
            $g['msg'] = 'Turn every light off. Type "col row" to press.';
        }
        $input = trim((string) ($in['input'] ?? ''));
        $key = strtoupper((string) ($in['key'] ?? ''));
        $done = !in_array(true, $g['grid'], true);

        if (!$done && preg_match('/^(\d+)\s+(\d+)$/', $input, $m)) {
            $c = (int) $m[1];
            $r = (int) $m[2];
            if ($c >= 1 && $c <= $N && $r >= 1 && $r <= $N) {
                $this->loToggle($g['grid'], ($r - 1) * $N + ($c - 1), $N);
                $g['moves']++;
                if (!in_array(true, $g['grid'], true)) {
                    $g['msg'] = 'All out in ' . $g['moves'] . ' moves!';
                    $this->saveScore($e, $g, $g['moves'], 'solved');
                } else {
                    $g['msg'] = $g['moves'] . ' moves so far.';
                }
            }
        }
        $done = !in_array(true, $g['grid'], true);
        if ($done && $key === 'ENTER') {
            $g = ['_module' => 'lightsout', '_saved' => $g['_saved'] ?? false];
            return $this->lightsout($e, [], $g);
        }

        $f = Frame::make('screen')->view('game')->title('Lights Out')->mode($done ? 'game' : 'line')
            ->header('Lights Out', $g['moves'] . ' moves')->blank()
            ->pipe('|15   ' . $g['msg'])->blank()
            ->pipe('|08      ' . implode(' ', range(1, $N)));
        for ($r = 0; $r < $N; $r++) {
            $line = '|08   ' . ($r + 1) . '  ';
            for ($c = 0; $c < $N; $c++) {
                $line .= ($g['grid'][$r * $N + $c] ? '|11#' : '|08.') . ' ';
            }
            $f->pipe($line);
        }
        $f->blank()->pipe($done ? '|10   ENTER for a new puzzle, ESC to leave.' : '|07   e.g.  3 2');
        return $f->prompt('Press')->footer('ESC leave');
    }

    private function loToggle(array &$grid, int $i, int $N): void
    {
        $r = intdiv($i, $N);
        $c = $i % $N;
        foreach ([[0, 0], [1, 0], [-1, 0], [0, 1], [0, -1]] as [$dr, $dc]) {
            $nr = $r + $dr;
            $nc = $c + $dc;
            if ($nr >= 0 && $nr < $N && $nc >= 0 && $nc < $N) {
                $grid[$nr * $N + $nc] = !$grid[$nr * $N + $nc];
            }
        }
    }
}
