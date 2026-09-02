<?php

declare(strict_types=1);

namespace Bbs\Core;

use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper. One shared connection per process.
 *
 * All queries use prepared statements; never interpolate user input into SQL.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $c   = Config::get('db');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $c['host'],
            (int) ($c['port'] ?? 3306),
            $c['name'],
            $c['charset'] ?? 'utf8mb4'
        );

        self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);

        self::$pdo->exec("SET time_zone = '+00:00'");

        return self::$pdo;
    }

    /** @param array<string,mixed>|list<mixed> $params */
    public static function q(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /**
     * @param array<string,mixed>|list<mixed> $params
     * @return array<string,mixed>|null
     */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::q($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<string,mixed>|list<mixed> $params
     * @return list<array<string,mixed>>
     */
    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    /** @param array<string,mixed>|list<mixed> $params */
    public static function val(string $sql, array $params = [], mixed $default = null): mixed
    {
        $v = self::q($sql, $params)->fetchColumn();
        return $v === false ? $default : $v;
    }

    /**
     * Insert an associative row into $table and return the new id.
     * @param array<string,mixed> $data
     */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $ph   = array_map(static fn ($c) => ':' . $c, $cols);
        $sql  = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $cols),
            implode(', ', $ph)
        );
        self::q($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Update rows in $table matching $where (equality only).
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     */
    public static function update(string $table, array $data, array $where): int
    {
        $set   = [];
        $bind  = [];
        foreach ($data as $k => $v) {
            $set[]        = "`$k` = :s_$k";
            $bind["s_$k"] = $v;
        }
        $cond = [];
        foreach ($where as $k => $v) {
            $cond[]       = "`$k` = :w_$k";
            $bind["w_$k"] = $v;
        }
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $set),
            implode(' AND ', $cond)
        );
        return self::q($sql, $bind)->rowCount();
    }

    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function tableExists(string $name): bool
    {
        return (bool) self::val(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$name]
        );
    }
}
