<?php

declare(strict_types=1);

namespace Statecraft\Audit;

use PDO;
use Statecraft\Exception\AuditTrailError;

/**
 * Locates the bundled DDL for the audit table, so it can be copied into a migration tool
 * or executed directly in tests and small applications.
 */
final class Migrations
{
    private const array FILES = [
        'sqlite' => 'sqlite.sql',
        'mysql' => 'mysql.sql',
        'pgsql' => 'postgresql.sql',
    ];

    private function __construct() {}

    /**
     * @param string $driver a PDO driver name: sqlite, mysql or pgsql
     */
    public static function path(string $driver): string
    {
        $file = self::FILES[$driver] ?? throw new AuditTrailError(\sprintf(
            'No audit migration for PDO driver "%s"; available: %s.',
            $driver,
            implode(', ', array_keys(self::FILES)),
        ));

        return \dirname(__DIR__, 2) . '/resources/migrations/' . $file;
    }

    public static function sql(string $driver): string
    {
        $sql = file_get_contents(self::path($driver));
        if ($sql === false) {
            throw new AuditTrailError(\sprintf('Cannot read the audit migration for "%s".', $driver));
        }

        return $sql;
    }

    /**
     * Runs the migration matching the connection's driver.
     */
    public static function apply(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $pdo->exec(self::sql(\is_string($driver) ? $driver : ''));
    }
}
