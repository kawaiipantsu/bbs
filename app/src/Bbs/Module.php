<?php

declare(strict_types=1);

namespace Bbs\Bbs;

/**
 * Base class for a BBS "module" - an interactive area reached from a menu item
 * with action=module. A module owns a slice of session state (Engine::$node->st)
 * and returns a Frame for every step.
 */
abstract class Module
{
    /** Slugs this module answers to (menu_items.target values). */
    abstract public static function slugs(): array;

    /**
     * Handle one step.
     *
     * @param Engine               $e     the running engine
     * @param string               $slug  which module slug was requested
     * @param array<string,mixed>  $in    client input: key, input, cmd, data
     * @param array<string,mixed>  $st    this module node's persisted sub-state (by ref)
     */
    abstract public function run(Engine $e, string $slug, array $in, array &$st): Frame;

    /** Convenience: a standard "permission denied" frame. */
    protected function denied(Engine $e, string $what = 'do that'): Frame
    {
        return Frame::make('screen')->title('Access Denied')->mode('pager')
            ->header('Access Denied')->blank()
            ->pipe('|12   You do not have clearance to ' . $what . '.')
            ->pipe('|08   Ask the SysOp about an upgrade, or open a ticket.')
            ->footer('ESC / Q  back');
    }

    /** Convenience: paginate a list of pipe-coded lines for 'pager' mode. */
    protected function pager(Engine $e, string $title, array $pipeLines, string $footer = 'SPACE page · Q / ESC back'): Frame
    {
        $f = Frame::make('screen')->title($title)->mode('pager')->header($title)->blank();
        foreach ($pipeLines as $l) {
            $f->pipe($l);
        }
        return $f->footer($footer);
    }

    /**
     * Append a keyboard-navigable choice list to a `mode('menu')` frame, with
     * the same highlight bar / ▸ marker / description footer the DB main menu
     * uses. The browser owns the arrow-key selection and turns ENTER into the
     * chosen item's hotkey - the module only has to render and handle the
     * hotkey it gets back.
     *
     * @param list<array{key:string,label:string,desc?:string}> $choices
     */
    protected function picker(Frame $f, array $choices, string $indent = '   '): Frame
    {
        $w    = Frame::width();
        $pad  = mb_strlen($indent);
        $items = $f->meta['items'] ?? [];
        foreach ($choices as $c) {
            $key   = (string) ($c['key'] ?? '');
            $label = (string) ($c['label'] ?? '');
            $row   = count($f->lines);
            $f->pipe($indent . '|08[|15' . $key . '|08] |07' . $label);
            $items[] = [
                'hotkey'      => $key,
                'label'       => $label,
                'description' => (string) ($c['desc'] ?? ''),
                'row'         => $row,
                'col'         => $pad,
                'w'           => max(24, min($w - $pad - 2, 6 + mb_strlen($label))),
            ];
        }
        $f->meta(['items' => $items]);
        return $f;
    }
}
