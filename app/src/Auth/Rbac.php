<?php

declare(strict_types=1);

namespace Bbs\Auth;

use Bbs\Core\Db;

/**
 * Role-based access control. Permissions are the union of every role a user
 * holds; guests get whatever the 'guest' role grants. Results are memoised per
 * request.
 */
final class Rbac
{
    /** @var array<int|string,list<string>> */
    private static array $permCache = [];

    /** @var array<int|string,int> */
    private static array $rankCache = [];

    /** @param array<string,mixed>|null $user */
    public static function permissions(?array $user): array
    {
        $key = $user['id'] ?? 'guest';
        if (isset(self::$permCache[$key])) {
            return self::$permCache[$key];
        }

        if ($user === null) {
            $rows = Db::all(
                "SELECT p.slug FROM permissions p
                 JOIN role_permissions rp ON rp.permission_id = p.id
                 JOIN roles r ON r.id = rp.role_id
                 WHERE r.slug = 'guest'"
            );
        } else {
            $rows = Db::all(
                "SELECT DISTINCT p.slug FROM permissions p
                 JOIN role_permissions rp ON rp.permission_id = p.id
                 JOIN user_roles ur ON ur.role_id = rp.role_id
                 WHERE ur.user_id = ?",
                [$user['id']]
            );
        }
        return self::$permCache[$key] = array_column($rows, 'slug');
    }

    /** @param array<string,mixed>|null $user */
    public static function can(?array $user, string $permission): bool
    {
        return in_array($permission, self::permissions($user), true);
    }

    /** @param array<string,mixed>|null $user */
    public static function rank(?array $user): int
    {
        $key = $user['id'] ?? 'guest';
        if (isset(self::$rankCache[$key])) {
            return self::$rankCache[$key];
        }
        if ($user === null) {
            return self::$rankCache[$key] = 0;
        }
        $rank = (int) Db::val(
            'SELECT COALESCE(MAX(r.`rank`),0) FROM roles r
             JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
            [$user['id']]
        );
        return self::$rankCache[$key] = $rank;
    }

    /** @param array<string,mixed>|null $user */
    public static function isStaff(?array $user): bool
    {
        return self::rank($user) >= 80;
    }

    /** @return list<array{slug:string,name:string,rank:int,color:string}> */
    public static function rolesOf(int $userId): array
    {
        return Db::all(
            'SELECT r.slug, r.name, r.`rank`, r.color FROM roles r
             JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = ? ORDER BY r.`rank` DESC',
            [$userId]
        );
    }

    public static function topRole(int $userId): ?array
    {
        return self::rolesOf($userId)[0] ?? null;
    }

    public static function grant(int $userId, string $roleSlug, ?int $by = null): void
    {
        $rid = Db::val('SELECT id FROM roles WHERE slug = ?', [$roleSlug]);
        if ($rid) {
            Db::q(
                'INSERT IGNORE INTO user_roles (user_id, role_id, granted_by) VALUES (?,?,?)',
                [$userId, $rid, $by]
            );
            self::flush();
        }
    }

    public static function revoke(int $userId, string $roleSlug): void
    {
        Db::q(
            'DELETE ur FROM user_roles ur JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = ? AND r.slug = ?',
            [$userId, $roleSlug]
        );
        self::flush();
    }

    public static function flush(): void
    {
        self::$permCache = [];
        self::$rankCache = [];
    }
}
