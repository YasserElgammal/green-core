<?php

namespace YasserElgammal\Green\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

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

    /** @deprecated Use ConnectionPool directly */
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
