<?php

declare(strict_types=1);

namespace Bbs\Http\Controllers;

use Bbs\Core\Config;
use Bbs\Core\Db;
use Bbs\Core\Request;
use Bbs\Core\Response;
use Bbs\Core\View;

/**
 * Serves the HTML "shell" - the CRT monitor, the terminal canvas host and the
 * boot loader. Deep-link routes render the same shell but with entity-specific
 * <meta> / OpenGraph tags and a `data-goto` hint the boot JS follows once the
 * modem "connects".
 */
final class PageController
{
    public function shell(Request $req): Response
    {
        return $this->page($req, $this->baseMeta());
    }

    public function board(Request $req, array $a): Response
    {
        $b = Db::one(
            'SELECT b.*, c.name AS conf FROM boards b JOIN conferences c ON c.id = b.conference_id WHERE b.slug = ?',
            [$a['slug']]
        );
        if (!$b) {
            return $this->page($req, $this->baseMeta());
        }
        return $this->page($req, [
            'title'       => $b['name'] . ' - ' . $this->siteName() . ' message base',
            'description' => $b['description'] ?: ('The ' . $b['name'] . ' board on ' . $this->siteName()),
            'canonical'   => $this->origin() . '/b/' . $b['slug'],
            'og_type'     => 'website',
            'og_image'    => $this->origin() . '/og/board-' . $b['slug'] . '.png',
            'goto'        => 'board:' . $b['slug'],
            'jsonld'      => [
                '@context' => 'https://schema.org',
                '@type'    => 'DiscussionForumPosting',
                'headline' => $b['name'],
                'about'    => $b['description'],
                'url'      => $this->origin() . '/b/' . $b['slug'],
            ],
        ]);
    }

    public function message(Request $req, array $a): Response
    {
        $m = Db::one(
            'SELECT m.*, b.name AS board FROM messages m JOIN boards b ON b.id = m.board_id
             WHERE m.id = ? AND m.deleted_at IS NULL',
            [(int) $a['id']]
        );
        if (!$m) {
            return $this->page($req, $this->baseMeta());
        }
        $snippet = mb_substr(trim(preg_replace('/\s+/', ' ', $m['body']) ?? ''), 0, 200);
        return $this->page($req, [
            'title'       => $m['subject'] . ' - ' . $m['board'] . ' - ' . $this->siteName(),
            'description' => $snippet,
            'canonical'   => $this->origin() . '/m/' . $m['id'],
            'og_type'     => 'article',
            'og_image'    => $this->origin() . '/og/msg-' . $m['id'] . '.png',
            'goto'        => 'msg:' . $m['id'],
            'jsonld'      => [
                '@context'      => 'https://schema.org',
                '@type'         => 'DiscussionForumPosting',
                'headline'      => $m['subject'],
                'articleBody'   => $snippet,
                'datePublished' => date('c', strtotime($m['created_at'])),
                'author'        => ['@type' => 'Person', 'name' => $m['from_handle']],
                'url'           => $this->origin() . '/m/' . $m['id'],
            ],
        ]);
    }

    public function profile(Request $req, array $a): Response
    {
        $u = Db::one('SELECT handle, tagline, location, created_at, posts, calls FROM users WHERE handle = ? AND deleted_at IS NULL', [$a['handle']]);
        if (!$u) {
            return $this->page($req, $this->baseMeta());
        }
        return $this->page($req, [
            'title'       => $u['handle'] . ' - user on ' . $this->siteName(),
            'description' => ($u['tagline'] ?: ('BBS caller since ' . date('M Y', strtotime($u['created_at'])))) .
                             ' · ' . $u['posts'] . ' posts · ' . $u['calls'] . ' calls',
            'canonical'   => $this->origin() . '/u/' . rawurlencode($u['handle']),
            'og_type'     => 'profile',
            'og_image'    => $this->origin() . '/og/user-' . rawurlencode($u['handle']) . '.png',
            'goto'        => 'user:' . $u['handle'],
        ]);
    }

    public function news(Request $req, array $a): Response
    {
        $cat = preg_replace('/[^a-z]/', '', strtolower($a['cat']));
        $names = ['it' => 'IT News', 'hacking' => 'Hacking News', 'tech' => 'Tech News', 'entertainment' => 'Entertainment News'];
        if (!isset($names[$cat])) {
            return $this->page($req, $this->baseMeta());
        }
        return $this->page($req, [
            'title'       => $names[$cat] . ' wire - ' . $this->siteName(),
            'description' => 'Live ' . $names[$cat] . ' headlines on the ' . $this->siteName() . ' news wire.',
            'canonical'   => $this->origin() . '/news/' . $cat,
            'og_type'     => 'website',
            'og_image'    => $this->origin() . '/og/news-' . $cat . '.png',
            'goto'        => 'news:' . $cat,
        ]);
    }

    public function game(Request $req, array $a): Response
    {
        $g = Db::one('SELECT slug, name, description FROM games WHERE slug = ? AND enabled = 1', [$a['slug']]);
        if (!$g) {
            return $this->page($req, $this->baseMeta());
        }
        return $this->page($req, [
            'title'       => $g['name'] . ' - door game on ' . $this->siteName(),
            'description' => $g['description'],
            'canonical'   => $this->origin() . '/g/' . $g['slug'],
            'og_type'     => 'website',
            'og_image'    => $this->origin() . '/og/game-' . $g['slug'] . '.png',
            'goto'        => 'game:' . $g['slug'],
        ]);
    }

    // -----------------------------------------------------------------
    private function page(Request $req, array $meta): Response
    {
        // Keep every route's tags inside the sizes search engines / social
        // cards actually render, so nothing shows up truncated.
        $meta['title']       = $this->tidy($meta['title'] ?? $this->siteName(), 70);
        $meta['description'] = $this->tidy($meta['description'] ?? '', 158);
        $meta['og_description'] = $this->tidy($meta['og_description'] ?? $meta['description'], 150);

        $html = View::render('shell', [
            'meta'     => $meta,
            'origin'   => $this->origin(),
            'site'     => $this->siteName(),
            'buildver' => Config::setting('version', '1.0.0'),
            'asset_v'  => $this->assetVersion(),
        ]);
        return Response::html($html)->withHeader('Cache-Control', 'no-cache');
    }

    /** Collapse whitespace, trim to $n chars on a word boundary, add an ellipsis. */
    private function tidy(string $s, int $n): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s) ?? $s);
        if (mb_strlen($s) <= $n) {
            return $s;
        }
        $cut = mb_substr($s, 0, $n - 1);
        $sp  = mb_strrpos($cut, ' ');
        if ($sp !== false && $sp > $n - 20) {
            $cut = mb_substr($cut, 0, $sp);
        }
        return rtrim($cut, " .,;:-") . '…';
    }

    private function baseMeta(): array
    {
        // ~55 char title, ~150 char description keep social/SERP previews intact.
        $title = Config::setting('seo_title', $this->siteName() . ' — an ANSI bulletin board inside a CRT');
        $desc  = Config::setting(
            'seo_description',
            'A keyboard-driven ANSI bulletin board that runs inside a CRT terminal — messages, files, door games, chat and a live news wire.'
        );
        return [
            'title'          => mb_substr($title, 0, 70),
            'description'    => mb_substr($desc, 0, 155),
            'og_description' => mb_substr($desc, 0, 150),
            'canonical'      => $this->origin() . '/',
            'og_type'        => 'website',
            'og_image'       => $this->origin() . '/media/images/og-default.png',
            'og_image_alt'   => $this->siteName() . ' — a keyboard-driven ANSI bulletin board inside a CRT terminal',
            'goto'           => '',
            'jsonld'         => [
                '@context' => 'https://schema.org',
                '@type'    => 'WebSite',
                'name'     => $this->siteName(),
                'url'      => $this->origin() . '/',
            ],
        ];
    }

    private function siteName(): string
    {
        return Config::setting('site_name', 'THUGS(red) BBS');
    }

    private function origin(): string
    {
        return rtrim((string) Config::get('canonical', 'https://bbs.thugs.red'), '/');
    }

    private function assetVersion(): string
    {
        // newest mtime across every shipped JS/CSS file, so any front-end
        // change busts the cache - including ES-module sub-imports, which the
        // shell's import map rewrites to carry this same ?v= tag.
        $root   = dirname(__DIR__, 3) . '/html';
        $newest = 0;
        foreach (['/js', '/css'] as $dir) {
            foreach (glob($root . $dir . '/*.{js,css}', GLOB_BRACE) ?: [] as $f) {
                $m = @filemtime($f);
                if ($m && $m > $newest) {
                    $newest = $m;
                }
            }
        }
        return substr((string) ($newest ?: time()), -6);
    }
}
