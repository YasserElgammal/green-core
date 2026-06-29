<?php

namespace YasserElgammal\Green\Cache\Drivers;

use YasserElgammal\Green\Cache\Contracts\CacheDriverInterface;
use Predis\Client;

class RedisDriver implements CacheDriverInterface
{
    private Client $redis;

    public function __construct(array $config)
    {
        $parameters = [
            'scheme'   => 'tcp',
            'host'     => $config['host'] ?? '127.0.0.1',
            'port'     => $config['port'] ?? 6379,
            'database' => $config['database'] ?? 1,
        ];

        if (!empty($config['password'])) {
            $parameters['password'] = $config['password'];
        }

        $options = [];
        if (!empty($config['prefix'])) {
            $options['prefix'] = $config['prefix'];
        }

        $this->redis = new Client($parameters, $options);
    }

    public function get(string $key): mixed
    {
        $value = $this->redis->get($key);

        if ($value === null) {
            return null;
        }

        return unserialize($value);
    }

    public function put(string $key, mixed $value, int $ttl): void
    {
        $this->redis->setex($key, $ttl, serialize($value));
    }

    public function forget(string $key): bool
    {
        return (bool) $this->redis->del([$key]);
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($key);
    }

    public function flush(): void
    {
        $this->redis->flushdb();
    }
}
