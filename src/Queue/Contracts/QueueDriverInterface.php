<?php
declare(strict_types=1);

namespace YasserElgammal\Green\Queue\Contracts;

interface QueueDriverInterface
{
    /**
     * Enqueue a payload. Returns the job identifier.
     */
    public function push(string $queue, string $payload, int $delay = 0): string|int;

    /**
     * Dequeue the next available job, or null if the queue is empty.
     *
     * @return array{id: string|int, payload: string, attempts: int}|null
     */
    public function pop(string $queue): ?array;

    /**
     * Acknowledge a successfully processed job.
     */
    public function delete(string $queue, string|int $id): void;

    /**
     * Return a failed job to the queue for retry.
     */
    public function release(string $queue, string|int $id, int $delay = 0): void;
}
