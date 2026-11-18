<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB as Database;

/**
 * Thin helper over Laravel's DB facade. Same method names as the old `Database` class of the
 * plain-PHP version, so the SQL in the services stays the same.
 *
 * ALWAYS pass user input through $params (prepared statements), never concatenate it into $sql.
 */
final class Db
{
    /** @return array<int, array<string, mixed>> */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return array_map(static fn ($row) => (array) $row, Database::select($sql, $params));
    }

    /** @return array<string, mixed>|null */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $rows = Database::select($sql, $params);
        return $rows === [] ? null : (array) $rows[0];
    }

    public static function fetchValue(string $sql, array $params = [])
    {
        $row = self::fetchOne($sql, $params);
        return $row === null ? null : reset($row);
    }

    /** INSERT / UPDATE / DELETE - returns affected rows. */
    public static function execute(string $sql, array $params = []): int
    {
        return Database::affectingStatement($sql, $params);
    }

    public static function lastInsertId(): int
    {
        return (int) Database::getPdo()->lastInsertId();
    }

    /** Run $fn inside a transaction; rolls back on any exception. */
    public static function transaction(Closure $fn)
    {
        return Database::transaction($fn);
    }
}
