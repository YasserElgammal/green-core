<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Scheduling\Mutex;

use YasserElgammal\Green\Scheduling\Contracts\MutexInterface;

final class InMemoryMutex implements MutexInterface
{
    /** @var array<string, int> */
    private array $locks = [];

    public function acquire(string $key, int $ttlSeconds): bool
    {
        $now = time();

        if (isset($this->locks[$key]) && $this->locks[$key] > $now) {
            return false;
        }

        $this->locks[$key] = $now + max(1, $ttlSeconds);

        return true;
    }

    public function release(string $key): void
    {
        unset($this->locks[$key]);
    }
}
