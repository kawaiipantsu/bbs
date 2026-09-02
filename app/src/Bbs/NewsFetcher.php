<?php

declare(strict_types=1);

namespace Bbs\Bbs;

use Bbs\Admin\AuditLog;
use Bbs\Core\Config;
use Bbs\Core\Db;

/**
 * Pulls RSS / Atom feeds listed in settings (news_feeds_<category>) into the
 * news_items table. Run from contrib/worker.php on a schedule, or on demand from
 * the SysOp News screen. Degrades quietly: a dead feed leaves the last cache in
 * place.
 */
final class NewsFetcher
{
    private const CATEGORIES = ['it', 'hacking', 'tech', 'entertainment'];

    /** @return int number of new items inserted across all categories */
    public static function run(?string $only = null): int
    {
        $inserted = 0;
        foreach (self::CATEGORIES as $cat) {
            if ($only && $only !== $cat) {
                continue;
            }
            $inserted += self::runCategory($cat);
        }
        return $inserted;
    }

    public static function runCategory(string $cat): int
    {
        $urls = array_values(array_filter(array_map(
            'trim',
            preg_split('/[\r\n]+/', Config::setting('news_feeds_' . $cat, '')) ?: []
        )));
        $max = Config::int('news_max_per_cat', 80);
        $new = 0;

        foreach ($urls as $url) {
            $xml = self::fetch($url);
            if ($xml === null) {
                continue;
            }
            foreach (self::parse($xml) as $item) {
                if ($item['url'] === '' || $item['title'] === '') {
                    continue;
                }
                $hash = sha1($item['url']);
                try {
                    $exists = Db::val('SELECT 1 FROM news_items WHERE url_hash = ?', [$hash]);
                    Db::q(
                        'INSERT INTO news_items (category, source, title, url, url_hash, summary, published_at, fetched_at)
                         VALUES (?,?,?,?,?,?,?,NOW())
                         ON DUPLICATE KEY UPDATE title = VALUES(title), summary = VALUES(summary), fetched_at = NOW()',
                        [
                            $cat,
                            mb_substr($item['source'] ?: self::host($url), 0, 78),
                            mb_substr($item['title'], 0, 298),
                            mb_substr($item['url'], 0, 598),
                            $hash,
                            mb_substr($item['summary'], 0, 2000),
                            $item['published'] ?: date('Y-m-d H:i:s'),
                        ]
                    );
                    if (!$exists) {
                        $new++;
                    }
                } catch (\Throwable $e) {
                    error_log('[BBS] news insert failed: ' . $e->getMessage());
                }
            }
        }

        // trim to the newest $max per category
        Db::q(
            "DELETE FROM news_items WHERE category = ? AND id NOT IN (
               SELECT id FROM (SELECT id FROM news_items WHERE category = ? ORDER BY published_at DESC, id DESC LIMIT ?) t
             )",
            [$cat, $cat, $max]
        );

        AuditLog::system('news.fetch', "$cat: +$new (from " . count($urls) . ' feeds)');
        return $new;
    }

    // -----------------------------------------------------------------
    private static function fetch(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'THUGS-red-BBS/1.0 (+https://bbs.thugs.red; news wire)',
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => ['Accept: application/rss+xml, application/atom+xml, application/xml, text/xml'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($body) || $body === '' || $code >= 400) {
            error_log("[BBS] news fetch $url -> HTTP $code");
            return null;
        }
        return $body;
    }

    /** @return list<array{title:string,url:string,summary:string,published:string,source:string}> */
    private static function parse(string $raw): array
    {
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if ($xml === false) {
            return [];
        }

        $out = [];

        // RSS 2.0
        if (isset($xml->channel)) {
            $source = trim((string) $xml->channel->title);
            foreach ($xml->channel->item as $it) {
                $out[] = [
                    'title'     => trim(html_entity_decode(strip_tags((string) $it->title))),
                    'url'       => trim((string) $it->link),
                    'summary'   => trim(mb_substr(html_entity_decode(strip_tags((string) $it->description)), 0, 500)),
                    'published' => self::date((string) ($it->pubDate ?? '')),
                    'source'    => $source,
                ];
            }
            return $out;
        }

        // Atom
        $ns = $xml->getNamespaces(true);
        $atom = $xml->children($ns['atom'] ?? 'http://www.w3.org/2005/Atom');
        $entries = $atom->entry ?? $xml->entry;
        $source = trim((string) ($atom->title ?? $xml->title));
        foreach ($entries as $it) {
            $link = '';
            foreach ($it->link as $l) {
                $rel = (string) $l['rel'];
                if ($rel === '' || $rel === 'alternate') {
                    $link = (string) $l['href'];
                    break;
                }
            }
            $out[] = [
                'title'     => trim(html_entity_decode(strip_tags((string) $it->title))),
                'url'       => $link,
                'summary'   => trim(mb_substr(html_entity_decode(strip_tags((string) ($it->summary ?? $it->content))), 0, 500)),
                'published' => self::date((string) ($it->updated ?? $it->published ?? '')),
                'source'    => $source,
            ];
        }
        return $out;
    }

    private static function date(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return date('Y-m-d H:i:s');
        }
        $ts = strtotime($s);
        return $ts ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
    }

    private static function host(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_HOST) ?: 'feed');
    }
}
