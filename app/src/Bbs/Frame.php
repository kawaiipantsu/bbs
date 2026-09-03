<?php

declare(strict_types=1);

namespace Bbs\Bbs;

use Bbs\Core\Config;

/**
 * Fluent builder for a terminal "frame" - the JSON payload the browser terminal
 * renders in response to /api/action. Also carries the shared chrome helpers
 * (header bar, rules, footer) so every screen looks like the same machine.
 */
final class Frame
{
    public string $view = 'screen';
    public string $title = '';
    public string $prompt = 'Command';
    public string $mode = 'pager';          // menu|pager|form|line|game|chat|redirect
    public ?string $sound = null;
    /** @var list<array<string,mixed>> */
    public array $fields = [];
    /** @var array<string,mixed> */
    public array $meta = [];
    /** @var list<list<array{s:string,f:int,b:int,o:bool,k:bool}>> */
    public array $lines = [];

    public static function make(string $view = 'screen'): self
    {
        $f = new self();
        $f->view = $view;
        return $f;
    }

    public static function width(): int
    {
        return Config::termCols();
    }

    public static function height(): int
    {
        return Config::termRows();
    }

    /** Rows available for list content, leaving `$chrome` lines for header/footer. */
    public static function pageSize(int $chrome = 8): int
    {
        return max(8, self::height() - $chrome);
    }

    // ---- fluent setters -------------------------------------------------
    public function view(string $v): self   { $this->view = $v; return $this; }
    public function title(string $v): self  { $this->title = $v; return $this; }
    public function prompt(string $v): self { $this->prompt = $v; return $this; }
    public function mode(string $v): self   { $this->mode = $v; return $this; }
    public function sound(?string $v): self { $this->sound = $v; return $this; }

    /** @param array<string,mixed> $m */
    public function meta(array $m): self { $this->meta = array_merge($this->meta, $m); return $this; }

    /** @param list<array<string,mixed>> $f */
    public function form(array $f, string $prompt = 'Fill in the form'): self
    {
        $this->mode = 'form';
        $this->fields = $f;
        $this->prompt = $prompt;
        return $this;
    }

    // ---- content ------------------------------------------------------
    /** Append one line from a pipe-coded string. */
    public function pipe(string $s): self
    {
        $rendered = AnsiRenderer::render($s, 'pipe');
        foreach ($rendered as $line) {
            $this->lines[] = $line;
        }
        return $this;
    }

    /** Append a whole pipe-coded block (may contain \n). */
    public function block(string $s, string $type = 'pipe', array $ctx = []): self
    {
        foreach (AnsiRenderer::render($s, $type, $ctx) as $line) {
            $this->lines[] = $line;
        }
        return $this;
    }

    /** Append pre-rendered lines. */
    public function raw(array $lines): self
    {
        foreach ($lines as $l) {
            $this->lines[] = $l;
        }
        return $this;
    }

    public function blank(int $n = 1): self
    {
        for ($i = 0; $i < $n; $i++) {
            $this->lines[] = [];
        }
        return $this;
    }

    public function text(string $s, int $fg = 7, int $bg = 0): self
    {
        $this->lines[] = [['s' => $s, 'f' => $fg, 'b' => $bg, 'o' => $fg >= 8, 'k' => false]];
        return $this;
    }

    public function center(string $pipeString): self
    {
        $plain = preg_replace('/\|\d{2}|\|CL/i', '', $pipeString) ?? $pipeString;
        $pad   = max(0, intdiv(self::width() - mb_strlen($plain), 2));
        return $this->pipe(str_repeat(' ', $pad) . $pipeString);
    }

    // ---- chrome -----------------------------------------------------
    public function header(string $left, string $right = ''): self
    {
        $w    = self::width();
        $name = ' ' . Config::setting('site_name', 'THUGS(red) BBS') . ' ';
        $l    = ' ' . $left . ' ';
        $r    = $right !== '' ? ' ' . $right . ' ' : '';
        $used = mb_strlen($name) + mb_strlen($l) + mb_strlen($r);
        $fill = max(1, $w - $used);
        $line = '|16|11' . $name . '|19|00' . $l . '|16|08' . str_repeat('·', $fill) . '|16|07' . $r;
        return $this->pipe($line)->pipe('|08' . str_repeat('─', $w));
    }

    public function rule(string $color = '|08'): self
    {
        return $this->pipe($color . str_repeat('─', self::width()));
    }

    public function footer(string $hint, string $right = ''): self
    {
        $this->pipe('|08' . str_repeat('─', self::width()));
        $line = '|08 ' . $hint;
        if ($right !== '') {
            $w   = self::width();
            $pad = max(2, $w - mb_strlen($hint) - mb_strlen($right) - 3);
            $line .= str_repeat(' ', $pad) . '|07' . $right . ' ';
        }
        return $this->pipe($line);
    }

    public function toArray(): array
    {
        return [
            'view'   => $this->view,
            'title'  => $this->title,
            'prompt' => $this->prompt,
            'mode'   => $this->mode,
            'sound'  => $this->sound,
            'fields' => $this->fields,
            'meta'   => $this->meta,
            'lines'  => $this->lines,
        ];
    }
}
