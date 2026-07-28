<?php

namespace YasserElgammal\Green\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Closure;
use RuntimeException;

/**
 * ConnectionPool — Named connection registry with lazy instantiation.
 *
 * Replaces the static singleton Database class with a proper registry
 * that supports multiple named database connections.
 */
class ConnectionPool
{
    /** @var array<string, Connection> Resolved connection instances */
    private array $connections = [];

    /** @var array<string, Closure> Custom connection factories */
    private array $customCreators = [];

    /**
     * @param array $config The full database config (default, connections map)
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * Get a connection by name, or the default.
     */
    public function connection(?string $name = null): Connection
    {
        $name = $name ?: ($this->config['default'] ?? 'mysql');

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        return $this->connections[$name] = $this->resolve($name);
    }

    /**
     * Register a custom connection factory.
     */
    public function extend(string $name, Closure $factory): void
    {
        $this->customCreators[$name] = $factory;
    }

    /**
     * Inject an existing connection for a given name (useful for testing).
     */
    public function setConnection(string $name, Connection $connection): void
    {
        $this->connections[$name] = $connection;
    }

    /**
     * Check if a named connection config exists.
     */
    public function hasConnection(string $name): bool
    {
        return isset($this->config['connections'][$name]) || isset($this->customCreators[$name]);
    }

    /**
     * Get all connection names that have been resolved.
     *
     * @return string[]
     */
    public function getActiveConnections(): array
    {
        return array_keys($this->connections);
    }

    /**
     * Resolve a connection from config or custom creator.
     */
    private function resolve(string $name): Connection
    {
        // Custom creator takes priority
        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($this->config['connections'][$name] ?? []);
        }

        $params = $this->config['connections'][$name] ?? null;

        if ($params === null) {
            throw new RuntimeException(
                "Database connection [{$name}] is not configured. " .
                "Available connections: [" . implode(', ', array_keys($this->config['connections'] ?? [])) . "]."
            );
        }

        return DriverManager::getConnection($params);
    }

}
