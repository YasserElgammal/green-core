<?php

namespace YasserElgammal\Green\Database;

use Doctrine\DBAL\Connection;

/**
 * Manages the Doctrine DBAL connection as a singleton.
 *
 * Now delegates to ConnectionPool for multi-connection support.
 * The static API is preserved for backward compatibility.
 *
 * A custom connection can be injected via setConnection()
 * to support in-memory SQLite databases during testing.
 */
class Database
{
    private static ?ConnectionPool $pool = null;

    /** @deprecated Backward-compatible override; use ConnectionPool directly. */
    private static ?Connection $legacyConnection = null;

    /**
     * Return the active connection, creating it from pool or env if needed.
     */
    public static function getConnection(?string $name = null): Connection
    {
        // Legacy override takes priority (for tests using setConnection)
        if ($name === null && self::$legacyConnection !== null) {
            return self::$legacyConnection;
        }

        return static::getPool()->connection($name);
    }

    /**
     * Get the ConnectionPool instance.
     */
    public static function getPool(): ConnectionPool
    {
        if (static::$pool === null) {
            // Fallback: build pool from env vars (backward compat)
            static::$pool = ConnectionPool::fromEnvironment();
        }

        return static::$pool;
    }

    /**
     * Run the callback inside a database transaction.
     *
     * @template TReturn
     * @param callable(): TReturn $callback
     * @return TReturn
     *
     * @throws \Throwable
     */
    public static function transaction(callable $callback, ?string $connection = null): mixed
    {
        $db = static::getConnection($connection);
        $db->beginTransaction();

        try {
            $result = $callback();
            $db->commit();
            return $result;
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    /**
     * Set the ConnectionPool instance (called by DatabaseServiceProvider).
     */
    public static function setPool(ConnectionPool $pool): void
    {
        static::$pool = $pool;
    }

    /**
     * Inject a custom connection (useful for tests using SQLite in-memory).
     *
     * Call Database::setConnection(null) in tearDown() to reset.
     */
    public static function setConnection(?Connection $connection): void
    {
        self::$legacyConnection = $connection;
    }
}
