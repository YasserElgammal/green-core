<?php

namespace YasserElgammal\Green\Routing\RateLimit;

class ArrayRateLimitStore implements RateLimitStoreInterface
{
    private array $records = [];

    public function get(string $key): ?array
    {
        return $this->records[$key] ?? null;
    }

    public function put(string $key, array $record): void
    {
        $this->records[$key] = $record;
    }

    public function forget(string $key): void
    {
        unset($this->records[$key]);
    }

    public function clearExpired(int $now): void
    {
        foreach ($this->records as $key => $record) {
            if (($record['reset_at'] ?? 0) <= $now) {
                unset($this->records[$key]);
            }
        }
    }
}