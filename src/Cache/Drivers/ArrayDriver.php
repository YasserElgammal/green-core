<?php

namespace YasserElgammal\Green\Cache\Drivers;

use YasserElgammal\Green\Cache\Contracts\CacheDriverInterface;

class ArrayDriver implements CacheDriverInterface
{
    private array $storage = [];

    public function get(string $key): mixed
    {
        if (!isset($this->storage[$key])) {
            return null;
        }

        $item = $this->storage[$key];

        if (time() >= $item['expires_at']) {
            unset($this->storage[$key]);
            return null;
        }

        return $item['value'];
    }

    public function put(string $key, mixed $value, int $ttl): void
    {
        $this->storage[$key] = [
            'value' => $value,
            'expires_at' => time() + $ttl,
        ];
    }

    public function forget(string $key): bool
    {
        if (isset($this->storage[$key])) {
            unset($this->storage[$key]);
            return true;
        }
        return false;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function flush(): void
    {
        $this->storage = [];
    }
}
