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
}
