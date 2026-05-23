<?php

namespace YasserElgammal\Green\Connect;

use YasserElgammal\Green\Connect\Contracts\ConnectDriverInterface;
use YasserElgammal\Green\Connect\Drivers\CurlDriver;
use YasserElgammal\Green\Connect\Drivers\FakeDriver;
use YasserElgammal\Green\Connect\Drivers\SymfonyHttpDriver;
use YasserElgammal\Green\Connect\Exceptions\InvalidConnectionException;

final class ConnectManager
{
    /**
     * @var array<string, ConnectDriverInterface>
     */
    private array $resolvedConnections = [];

    /**
     * @var array<string, callable(array): ConnectDriverInterface>
     */
    private array $customCreators = [];

    /**
     * @param array{
     *     default?: string,
     *     connections?: array<string,array<string,mixed>>
     * } $config
     */
    public function __construct(private readonly array $config = [])
    {
    }

    public function request(?string $connection = null): PendingRequest
    {
        $connection = $connection ?? $this->getDefaultConnection();

        return new PendingRequest($this, $connection, $this->getConnectionConfig($connection));
    }

    public function driver(?string $connection = null): ConnectDriverInterface
    {
        $connection = $connection ?? $this->getDefaultConnection();

        if (!isset($this->resolvedConnections[$connection])) {
            $this->resolvedConnections[$connection] = $this->resolve($connection);
        }

        return $this->resolvedConnections[$connection];
    }

    public function getDefaultConnection(): string
    {
        return $this->config['default'] ?? 'default';
    }

    /**
     * @return array<string,mixed>
     */
    public function getConnectionConfig(string $connection): array
    {
        $connectionConfig = $this->config['connections'][$connection] ?? null;

        if ($connectionConfig === null) {
            throw new InvalidConnectionException($connection);
        }

        return $connectionConfig;
    }

    public function extend(string $driver, callable $creator): void
    {
        $this->customCreators[$driver] = $creator;
    }

    public function fake(?string $connection = null): FakeDriver
    {
        $connection = $connection ?? $this->getDefaultConnection();

        $fake = new FakeDriver();
        $this->resolvedConnections[$connection] = $fake;

        return $fake;
    }

    /**
     * @return array<string,mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return string[]
     */
    public function getResolvedConnections(): array
    {
        return array_keys($this->resolvedConnections);
    }

    public function purge(?string $connection = null): void
    {
        if ($connection === null) {
            $this->resolvedConnections = [];
            return;
        }

        unset($this->resolvedConnections[$connection]);
    }

    private function resolve(string $connection): ConnectDriverInterface
    {
        $connectionConfig = $this->getConnectionConfig($connection);
        $driverType = $connectionConfig['driver'] ?? 'symfony';

        if (isset($this->customCreators[$driverType])) {
            $driver = ($this->customCreators[$driverType])($connectionConfig);

            if (!$driver instanceof ConnectDriverInterface) {
                throw new \RuntimeException(
                    "Custom connect driver creator for '{$driverType}' must return a ConnectDriverInterface instance."
                );
            }

            return $driver;
        }

        return match ($driverType) {
            'symfony' => new SymfonyHttpDriver(),
            'curl' => new CurlDriver(),
            default => throw new \RuntimeException(
                "Unsupported connect driver: '{$driverType}'. "
                . 'Register a custom creator via ConnectManager::extend().'
            ),
        };
    }
}
