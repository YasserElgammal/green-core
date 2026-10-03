<?php
declare(strict_types=1);

namespace YasserElgammal\Green\Queue;

use YasserElgammal\Green\Queue\Contracts\JobInterface;
use YasserElgammal\Green\Queue\Contracts\QueueDriverInterface;
use Throwable;

final class Worker
{
    private bool $shouldStop = false;

    public function __construct(private readonly QueueManager $manager) {}

    /**
     * Process the next available job.
     *
     * Returns true if a job was processed, false if the queue was empty.
     */
    public function processNext(
        string $queue = 'default',
        ?string $connection = null,
    ): bool {
        $driver = $this->manager->connection($connection);
        $raw = $driver->pop($queue);

        if ($raw === null) {
            return false;
        }

        try {
            $job = $this->manager->decode($raw['payload']);
            
            $job->handle();
            $driver->delete($queue, $raw['id']);
        } catch (Throwable $e) {
            // For decoding failures, we need to extract maxAttempts/retryDelay safely or use defaults
            $job = isset($job) ? $job : null;
            $this->handleFailure($driver, $queue, $raw, $job, $e);
        }

        return true;
    }

    /**
     * Run the worker loop.
     */
    public function run(
        string $queue = 'default',
        ?string $connection = null,
        int $sleep = 3,
        int $maxJobs = 0,
    ): void {
        $processed = 0;

        while (!$this->shouldStop) {
            if (!$this->processNext($queue, $connection)) {
                sleep($sleep);
                continue;
            }

            $processed++;

            if ($maxJobs > 0 && $processed >= $maxJobs) {
                break;
            }
        }
    }

    /** Signal the worker to stop after the current job. */
    public function stop(): void
    {
        $this->shouldStop = true;
    }

    private function handleFailure(
        QueueDriverInterface $driver,
        string $queue,
        array $raw,
        ?JobInterface $job,
        Throwable $e
    ): void {
        // Defaults if job decoding failed
        $maxAttempts = $job ? $job->maxAttempts() : 1;
        $retryDelay = $job ? $job->retryDelay() : 0;

        if ($raw['attempts'] >= $maxAttempts) {
            // Discard job (delete it)
            $driver->delete($queue, $raw['id']);
            
            // In a real system, you'd log this or move to a failed_jobs table
            // For now, Green just discards it.
        } else {
            // Release back to the queue for retry
            $driver->release($queue, $raw['id'], $retryDelay);
        }
    }
}
