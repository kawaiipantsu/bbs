<?php

declare(strict_types=1);

namespace Bbs\Core;

/**
 * Job queue facade. Uses beanstalkd when available; otherwise writes the job to
 * the `jobs_log` table with status 'pending' so contrib/worker.php (cron mode)
 * can still pick it up. Callers never need to care which path is active.
 */
final class Queue
{
    public const TUBE_DISCORD = 'discord';
    public const TUBE_MAIL    = 'mail';
    public const TUBE_NEWS    = 'news';
    public const TUBE_SYSTEM  = 'system';

    public static function push(string $tube, array $payload, int $delay = 0): void
    {
        $payload['_queued_at'] = time();

        if (Beanstalk::enabled()) {
            try {
                Beanstalk::fromConfig()->put($tube, $payload, 1024, $delay, 120);
                return;
            } catch (\Throwable $e) {
                error_log('[BBS] queue: beanstalk put failed, falling back to DB: ' . $e->getMessage());
            }
        }

        try {
            Db::insert('jobs_log', [
                'tube'        => $tube,
                'payload'     => json_encode($payload, JSON_UNESCAPED_SLASHES),
                'status'      => 'pending',
                'run_after'   => date('Y-m-d H:i:s', time() + $delay),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('[BBS] queue: DB fallback failed: ' . $e->getMessage());
        }
    }
}
