<?php

namespace YasserElgammal\Green\Cache\Contracts;

interface CacheDriverInterface
{
    /**
     * Retrieve an item from the cache by key.
     *
     * @param string $key
     * @return mixed Returns null if the item does not exist or is expired.
     */
    public function get(string $key): mixed;

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl Time to live in seconds.
     */
    public function put(string $key, mixed $value, int $ttl): void;

    /**
     * Remove an item from the cache.
     *
     * @param string $key
     * @return bool True if the item was successfully removed.
     */
    public function forget(string $key): bool;

    /**
     * Check if an item exists in the cache and has not expired.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Remove all items from the cache.
     */
    public function flush(): void;
}
