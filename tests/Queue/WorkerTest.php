<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Queue;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use YasserElgammal\Green\Queue\Contracts\JobInterface;
use YasserElgammal\Green\Queue\Contracts\QueueDriverInterface;
use YasserElgammal\Green\Queue\QueueManager;
use YasserElgammal\Green\Queue\Worker;

final class WorkerTest extends TestCase
{
    public function testItReturnsFalseWhenTheQueueIsEmpty(): void
    {
        [$worker, $driver] = $this->workerWith([]);

        self::assertFalse($worker->processNext('emails'));
        self::assertSame([], $driver->deleted);
    }

    public function testItHandlesAndDeletesASuccessfulJob(): void
    {
        WorkerSuccessJob::$handled = 0;
        $manager = new QueueManager([]);
        [$worker, $driver] = $this->workerWith([
            ['id' => 7, 'payload' => $manager->encode(new WorkerSuccessJob()), 'attempts' => 1],
        ]);

        self::assertTrue($worker->processNext('emails'));
        self::assertSame(1, WorkerSuccessJob::$handled);
        self::assertSame([['emails', 7]], $driver->deleted);
    }

    public function testItReleasesAFailedJobWhileAttemptsRemain(): void
    {
        $manager = new QueueManager([]);
        [$worker, $driver] = $this->workerWith([
            ['id' => 8, 'payload' => $manager->encode(new WorkerFailingJob()), 'attempts' => 1],
        ]);

        self::assertTrue($worker->processNext());
        self::assertSame([['default', 8, 12]], $driver->released);
        self::assertSame([], $driver->deleted);
    }

    public function testItDeletesAFailedJobAfterTheMaximumAttempts(): void
    {
        $manager = new QueueManager([]);
        [$worker, $driver] = $this->workerWith([
            ['id' => 9, 'payload' => $manager->encode(new WorkerFailingJob()), 'attempts' => 3],
        ]);

        self::assertTrue($worker->processNext());
        self::assertSame([['default', 9]], $driver->deleted);
        self::assertSame([], $driver->released);
    }

    public function testInvalidPayloadIsDeletedUsingSafeDefaults(): void
    {
        [$worker, $driver] = $this->workerWith([
            ['id' => 10, 'payload' => '{}', 'attempts' => 1],
        ]);

        self::assertTrue($worker->processNext());
        self::assertSame([['default', 10]], $driver->deleted);
    }

    private function workerWith(array $jobs): array
    {
        $driver = new WorkerQueueDriver($jobs);
        $manager = new QueueManager([
            'default' => 'worker',
            'connections' => ['worker' => ['driver' => 'worker_fake']],
        ]);
        $manager->extend('worker_fake', fn (): QueueDriverInterface => $driver);

        return [new Worker($manager), $driver];
    }
}

final class WorkerQueueDriver implements QueueDriverInterface
{
    public array $deleted = [];
    public array $released = [];
    public function __construct(private array $jobs) {}
    public function push(string $queue, string $payload, int $delay = 0): string|int { return 1; }
    public function pop(string $queue): ?array { return array_shift($this->jobs); }
    public function delete(string $queue, string|int $id): void { $this->deleted[] = [$queue, $id]; }
    public function release(string $queue, string|int $id, int $delay = 0): void { $this->released[] = [$queue, $id, $delay]; }
}

final class WorkerSuccessJob implements JobInterface
{
    public static int $handled = 0;
    public function handle(): void { self::$handled++; }
    public function maxAttempts(): int { return 1; }
    public function retryDelay(): int { return 0; }
}

final class WorkerFailingJob implements JobInterface
{
    public function handle(): void { throw new RuntimeException('Job failed.'); }
    public function maxAttempts(): int { return 3; }
    public function retryDelay(): int { return 12; }
}
