<?php

declare(strict_types=1);

namespace Bbs\Admin;

use Bbs\Auth\Session;
use Bbs\Core\Db;

/**
 * Append-only audit trail. Every state-changing action in the BBS should call
 * AuditLog::record(). Viewed in SysOp -> Audit Log.
 */
final class AuditLog
{
    private static ?Session $session = null;

    public static function bind(Session $s): void
    {
        self::$session = $s;
    }

    /**
     * @param array<string,mixed> $meta
     */
    public static function record(
        string $action,
        string $targetType = '',
        string|int $targetId = '',
        string $summary = '',
        array $meta = []
    ): void {
        $s = self::$session;
        try {
            Db::insert('audit_log', [
                'actor_user_id' => $s?->userId,
                'actor_handle'  => $s?->handle() ?? 'system',
                'ip'            => $s->ip ?? '',
                'ip_phone'      => $s->ipPhone ?? '',
                'action'        => $action,
                'target_type'   => $targetType,
                'target_id'     => (string) $targetId,
                'summary'       => mb_substr($summary, 0, 255),
                'meta'          => $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES) : null,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('[BBS] audit write failed: ' . $e->getMessage());
        }
    }

    /** System-initiated event (cron, worker) with no session. */
    public static function system(string $action, string $summary = '', array $meta = []): void
    {
        try {
            Db::insert('audit_log', [
                'actor_user_id' => null,
                'actor_handle'  => 'system',
                'ip'            => '',
                'ip_phone'      => '',
                'action'        => $action,
                'target_type'   => '',
                'target_id'     => '',
                'summary'       => mb_substr($summary, 0, 255),
                'meta'          => $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES) : null,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('[BBS] audit(system) write failed: ' . $e->getMessage());
        }
    }
}
