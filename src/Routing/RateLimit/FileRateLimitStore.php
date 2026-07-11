<?php

namespace YasserElgammal\Green\Routing\RateLimit;

class FileRateLimitStore implements RateLimitStoreInterface
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0777, true);
        }
    }

    public function get(string $key): ?array
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return null;
        }

        $record = json_decode((string) @file_get_contents($path), true);
        return is_array($record) ? $record : null;
    }

    public function put(string $key, array $record): void
    {
        @file_put_contents($this->path($key), json_encode($record, JSON_THROW_ON_ERROR), LOCK_EX);
    }

    public function forget(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function clearExpired(int $now): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
            $record = json_decode((string) @file_get_contents($path), true);
            if (!is_array($record) || ($record['reset_at'] ?? 0) <= $now) {
                @unlink($path);
            }
        }
    }

    private function path(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    }
}