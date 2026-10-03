<?php
declare(strict_types=1);

namespace YasserElgammal\Green\Queue\Drivers;

use YasserElgammal\Green\Queue\Contracts\QueueDriverInterface;

final class SyncDriver implements QueueDriverInterface
{
    public function push(string $queue, string $payload, int $delay = 0): string|int
    {
        // Decode and execute immediately
        $data = json_decode($payload, true);
        $job = unserialize($data['data']);
        $job->handle();
        
        return uniqid('sync_', true);
    }

    public function pop(string $queue): ?array
    {
        return null;
    }

    public function delete(string $queue, string|int $id): void
    {
        // No-op for sync driver
    }

    public function release(string $queue, string|int $id, int $delay = 0): void
    {
        // No-op for sync driver
    }
}
