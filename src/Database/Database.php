<?php

namespace YasserElgammal\Green\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * Manages the Doctrine DBAL connection as a singleton.
 *
 * A custom connection can be injected via setConnection()
 * to support in-memory SQLite databases during testing.
 */
class Database
{
    private static ?Connection $connection = null;

    /**
     * Return the active connection, creating it from .env if needed.
     */
    public static function getConnection(): Connection
    {
        if (self::$connection === null) {
            $connectionParams = [
                'dbname'   => $_ENV['DB_NAME']     ?? 'green_framework',
                'user'     => $_ENV['DB_USER']     ?? 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'host'     => $_ENV['DB_HOST']     ?? '127.0.0.1',
                'port'     => (int) ($_ENV['DB_PORT'] ?? 3306),
                'driver'   => $_ENV['DB_DRIVER']   ?? 'pdo_mysql',
            ];

            self::$connection = DriverManager::getConnection($connectionParams);
        }

        return self::$connection;
    }

    /**
     * Inject a custom connection (useful for tests using SQLite in-memory).
     *
     * Call Database::setConnection(null) in tearDown() to reset.
     */
    public static function setConnection(?Connection $connection): void
    {
        self::$connection = $connection;
    }
}
