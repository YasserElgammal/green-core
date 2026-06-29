<?php

namespace YasserElgammal\Green\Cache;

use YasserElgammal\Green\Cache\Contracts\CacheDriverInterface;
use RuntimeException;
use Closure;

class CacheManager
{
    /** @var array<string, CacheDriverInterface> */
    private array $stores = [];

    /** @var array<string, Closure> */
    private array $customCreators = [];

    public function __construct(private readonly array $config) {}

    /**
     * Get a cache store instance by name, or the default.
     */
    public function store(?string $name = null): CacheDriverInterface
    {
        $name = $name ?: ($this->config['default'] ?? 'file');

        if (!isset($this->stores[$name])) {
            $this->stores[$name] = $this->resolve($name);
        }

        return $this->stores[$name];
    }

    /**
     * Register a custom driver creator Closure.
     */
    public function extend(string $driver, Closure $callback): void
    {
        $this->customCreators[$driver] = $callback;
    }

    /**
     * Resolve the given store.
     */
    private function resolve(string $name): CacheDriverInterface
    {
        $config = $this->config['stores'][$name] ?? null;

        if (is_null($config)) {
            throw new RuntimeException("Cache store [{$name}] is not defined.");
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (isset($this->customCreators[$config['driver']])) {
            return ($this->customCreators[$config['driver']])($config);
        }

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        }

        throw new RuntimeException("Driver [{$config['driver']}] is not supported.");
    }

    private function createFileDriver(array $config): CacheDriverInterface
    {
        return new Drivers\FileDriver($config['path'] ?? '/tmp/cache');
    }

    private function createArrayDriver(array $config): CacheDriverInterface
    {
        return new Drivers\ArrayDriver();
    }

    private function createDatabaseDriver(array $config): CacheDriverInterface
    {
        // Database connection pool must be initialized, using Database/ConnectionPool later
        // Fallback to static Database connection for now, we will update in 3.4
        $connection = \YasserElgammal\Green\Database\Database::getConnection($config['connection'] ?? null);
        return new Drivers\DatabaseDriver($connection, $config['table'] ?? 'cache_store');
    }

    private function createRedisDriver(array $config): CacheDriverInterface
    {
        // Inject global cache prefix if provided in config
        $config['prefix'] = $this->config['prefix'] ?? '';
        return new Drivers\RedisDriver($config);
    }

    // Proxy methods to default store
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->store()->get($key);
        return $value !== null ? $value : $default;
    }

    public function put(string $key, mixed $value, int $ttl): void
    {
        $this->store()->put($key, $value, $ttl);
    }

    public function forget(string $key): bool
    {
        return $this->store()->forget($key);
    }

    public function has(string $key): bool
    {
        return $this->store()->has($key);
    }

    public function flush(): void
    {
        $this->store()->flush();
    }

    public function remember(string $key, int $ttl, Closure $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }
}
