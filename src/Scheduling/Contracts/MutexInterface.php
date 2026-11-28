<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Scheduling\Contracts;

interface MutexInterface
{
    /**
     * Attempt to acquire a lock for the given key.
     */
    public function acquire(string $key, int $ttlSeconds): bool;

    /**
     * Release the lock for the given key.
     */
    public function release(string $key): void;
}
