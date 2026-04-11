<?php

namespace YasserElgammal\Green\Database\Schema;

use PDO;

/**
 * Schema is the entry point for all DDL operations.
 *
 * Usage:
 *   Schema::create('users', function (Blueprint $t) { ... });
 *   Schema::table('users', function (Blueprint $t) { ... });
 *   Schema::drop('users');
 *   Schema::dropIfExists('users');
 */
class Schema
{
    private static ?PDO  $pdo    = null;
    private static bool  $dryRun = false;
    private static bool  $safe   = false;

    /** @var string[] Collected SQL in dry-run mode */
    private static array $dryRunLog = [];

    // ─── Configuration ─────────────────────────────────────────────────────

    public static function setPdo(PDO $pdo): void
    {
        static::$pdo = $pdo;
    }

    public static function setDryRun(bool $dryRun): void
    {
        static::$dryRun = $dryRun;
    }

    public static function setSafeMode(bool $safe): void
    {
        static::$safe = $safe;
    }

    public static function getDryRunLog(): array
    {
        return static::$dryRunLog;
    }

    public static function clearDryRunLog(): void
    {
        static::$dryRunLog = [];
    }

    // ─── Public API ────────────────────────────────────────────────────────

    /**
     * Create a new table.
     */
    public static function create(string $table, callable $callback): void
    {
        static::ensurePdo();

        $blueprint = new Blueprint($table, static::$pdo, static::$dryRun, static::$safe);
        $callback($blueprint);

        $statements = $blueprint->buildCreateStatements();
        static::executeAll($statements);
    }

    /**
     * Modify an existing table.
     */
    public static function table(string $table, callable $callback): void
    {
        static::ensurePdo();

        $blueprint = new Blueprint($table, static::$pdo, static::$dryRun, static::$safe);
        $callback($blueprint);

        $statements = $blueprint->buildAlterStatements();
        static::executeAll($statements);
    }

    /**
     * Drop a table (blocked in safe mode).
     */
    public static function drop(string $table): void
    {
        if (static::$safe) {
            throw new \RuntimeException(
                "Safe mode: DROP TABLE `{$table}` requires --force."
            );
        }

        static::executeAll(["DROP TABLE `{$table}`"]);
    }

    /**
     * Drop a table if it exists (blocked in safe mode).
     */
    public static function dropIfExists(string $table): void
    {
        if (static::$safe) {
            throw new \RuntimeException(
                "Safe mode: DROP TABLE IF EXISTS `{$table}` requires --force."
            );
        }

        static::executeAll(["DROP TABLE IF EXISTS `{$table}`"]);
    }

    /**
     * Check whether a table exists.
     */
    public static function hasTable(string $table): bool
    {
        static::ensurePdo();

        $dbName = static::$pdo->query('SELECT DATABASE()')->fetchColumn();
        $stmt   = static::$pdo->prepare("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ");
        $stmt->execute([$dbName, $table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Check whether a column exists.
     */
    public static function hasColumn(string $table, string $column): bool
    {
        static::ensurePdo();

        $dbName = static::$pdo->query('SELECT DATABASE()')->fetchColumn();
        $stmt   = static::$pdo->prepare("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ");
        $stmt->execute([$dbName, $table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Drop all tables in the current database.
     */
    public static function dropAllTables(): void
    {
        static::ensurePdo();

        if (static::$safe) {
            throw new \RuntimeException("Safe mode: DROP ALL TABLES requires --force.");
        }

        $driver = static::$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            static::executeAll(["SET FOREIGN_KEY_CHECKS = 0;"]);

            $dbName = static::$pdo->query('SELECT DATABASE()')->fetchColumn();
            $stmt = static::$pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?");
            $stmt->execute([$dbName]);
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                static::executeAll(["DROP TABLE `{$table}`"]);
            }

            static::executeAll(["SET FOREIGN_KEY_CHECKS = 1;"]);
        } elseif ($driver === 'sqlite') {
            $stmt = static::$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name != 'sqlite_sequence'");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            static::executeAll(["PRAGMA foreign_keys = OFF;"]);
            foreach ($tables as $table) {
                static::executeAll(["DROP TABLE `{$table}`"]);
            }
            static::executeAll(["PRAGMA foreign_keys = ON;"]);
        }
    }

    // ─── Private Helpers ───────────────────────────────────────────────────

    private static function executeAll(array $statements): void
    {
        foreach ($statements as $sql) {
            if (static::$dryRun) {
                static::$dryRunLog[] = $sql;
            } else {
                static::$pdo->exec($sql);
            }
        }
    }

    private static function ensurePdo(): void
    {
        if (static::$pdo === null) {
            throw new \RuntimeException('No PDO connection set on Schema. Call Schema::setPdo($pdo).');
        }
    }
}
