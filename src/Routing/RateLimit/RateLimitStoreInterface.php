<?php

namespace YasserElgammal\Green\Routing\RateLimit;

interface RateLimitStoreInterface
{
    public function get(string $key): ?array;
    public function put(string $key, array $record): void;
    public function forget(string $key): void;
    public function clearExpired(int $now): void;
}