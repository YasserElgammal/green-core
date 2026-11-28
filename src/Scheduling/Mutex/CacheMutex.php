<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Scheduling\Mutex;

use YasserElgammal\Green\Cache\Contracts\CacheDriverInterface;
use YasserElgammal\Green\Scheduling\Contracts\MutexInterface;

/**
 * Best-effort single-host mutex backed by a Green cache driver.
 */
final class CacheMutex implements MutexInterface
{
    public function __construct(
        private readonly CacheDriverInterface $cache,
        private readonly string $prefix = 'green:schedule:lock:',
    ) {
    }

    public function acquire(string $key, int $ttlSeconds): bool
    {
        $cacheKey = $this->prefix . $key;

        if ($this->cache->has($cacheKey)) {
            return false;
        }

        $this->cache->put($cacheKey, 1, max(1, $ttlSeconds));

        return true;
    }

    public function release(string $key): void
    {
        $this->cache->forget($this->prefix . $key);
    }
}
