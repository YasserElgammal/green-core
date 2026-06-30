<?php

namespace YasserElgammal\Green\Routing;

use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Routing\RateLimit\RateLimitStoreInterface;

class RateLimiter
{
    public function __construct(
        private readonly RateLimitStoreInterface $store,
        private readonly mixed $clock = null,
    ) {
    }

    public function key(Request $request, ?string $prefix = null): string
    {
        $userId = $this->userId($request);
        $identity = $userId !== null ? 'user:' . $userId : 'ip:' . $request->ip();

        return $prefix !== null && $prefix !== '' ? $prefix . ':' . $identity : $identity;
    }

    public function hit(string $key, int $maxAttempts, int $decaySeconds): array
    {
        $now = $this->now();
        $record = $this->store->get($key);

        if (!$record || ($record['reset_at'] ?? 0) <= $now) {
            $record = ['attempts' => 0, 'reset_at' => $now + $decaySeconds];
        }

        $record['attempts']++;
        $this->store->put($key, $record);

        return $this->state($record, $maxAttempts, $now);
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $record = $this->store->get($key);
        return $record !== null && ($record['reset_at'] ?? 0) > $this->now() && ($record['attempts'] ?? 0) >= $maxAttempts;
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        $record = $this->store->get($key) ?? ['attempts' => 0];
        return max(0, $maxAttempts - (int) ($record['attempts'] ?? 0));
    }

    public function retryAfter(string $key): int
    {
        $record = $this->store->get($key);
        return max(0, (int) (($record['reset_at'] ?? $this->now()) - $this->now()));
    }

    public function clearExpired(): void
    {
        $this->store->clearExpired($this->now());
    }

    private function state(array $record, int $maxAttempts, int $now): array
    {
        return [
            'attempts' => (int) $record['attempts'],
            'remaining' => max(0, $maxAttempts - (int) $record['attempts']),
            'retry_after' => max(0, (int) $record['reset_at'] - $now),
            'reset_at' => (int) $record['reset_at'],
            'exceeded' => (int) $record['attempts'] > $maxAttempts,
        ];
    }

    private function now(): int
    {
        return is_callable($this->clock) ? (int) ($this->clock)() : time();
    }

    private function userId(Request $request): int|string|null
    {
        $user = $request->getAttribute('user');
        if (is_object($user)) {
            if (isset($user->id)) {
                return $user->id;
            }
            if (method_exists($user, 'getId')) {
                return $user->getId();
            }
        }

        return $request->getAttribute('user_id') ?? $request->session()->get('user_id');
    }
}