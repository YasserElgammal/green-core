<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Scheduling;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use YasserElgammal\Green\Queue\Contracts\JobInterface;
use YasserElgammal\Green\Queue\Contracts\QueueDriverInterface;
use YasserElgammal\Green\Queue\QueueManager;
use YasserElgammal\Green\Scheduling\Mutex\InMemoryMutex;
use YasserElgammal\Green\Scheduling\Schedule;
use YasserElgammal\Green\Scheduling\ScheduleRunner;
use YasserElgammal\Green\Scheduling\TaskRunResult;
use YasserElgammal\Green\Signal\LifecycleSignals;
use YasserElgammal\Green\Signal\SignalDispatcher;

final class ScheduleRunnerTest extends TestCase
{
    public function test_runs_due_tasks_and_skips_non_due_tasks(): void
    {
        $executed = [];

        $schedule = new Schedule();
        $schedule->call(function () use (&$executed): void {
            $executed[] = 'due';
        }, 'due-task')->everyMinute();

        $schedule->call(function () use (&$executed): void {
            $executed[] = 'not-due';
        }, 'not-due-task')->dailyAt('03:00');

        $runner = new ScheduleRunner(new InMemoryMutex());
        $results = $runner->run($schedule, new DateTimeImmutable('2026-11-27 14:05:00'));

        $this->assertSame(['due'], $executed);
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->succeeded());
    }

    public function test_dispatches_jobs_and_runs_commands(): void
    {
        $dispatched = new \ArrayObject();
        $queue = new QueueManager([
            'default' => 'spy',
            'connections' => [
                'spy' => ['driver' => 'spy'],
            ],
        ]);
        $queue->extend('spy', static fn (): QueueDriverInterface => new class ($dispatched) implements QueueDriverInterface {
            public function __construct(private readonly \ArrayObject $dispatched)
            {
            }

            public function push(string $queue, string $payload, int $delay = 0): string|int
            {
                $this->dispatched->append([$queue, $payload]);
                return 'spy-1';
            }

            public function pop(string $queue): ?array
            {
                return null;
            }

            public function delete(string $queue, string|int $id): void
            {
            }

            public function release(string $queue, string|int $id, int $delay = 0): void
            {
            }
        });

        $ranCommands = [];
        $runner = new ScheduleRunner(
            mutex: new InMemoryMutex(),
            queue: $queue,
            commandRunner: function (string $command, array $arguments) use (&$ranCommands): int {
                $ranCommands[] = [$command, $arguments];
                return 0;
            },
        );

        $job = new DummyScheduledJob();

        $schedule = new Schedule();
        $schedule->command('view:clear', ['--quiet' => true])->everyMinute();
        $schedule->job($job, 'emails', 'spy')->everyMinute();

        $results = $runner->run($schedule, new DateTimeImmutable('2026-11-27 14:05:00'));

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->succeeded());
        $this->assertTrue($results[1]->succeeded());
        $this->assertSame([['view:clear', ['--quiet' => true]]], $ranCommands);
        $this->assertCount(1, $dispatched);
        $this->assertSame('emails', $dispatched[0][0]);
    }

    public function test_without_overlapping_prevents_concurrent_runs_and_releases_on_failure(): void
    {
        $mutex = new InMemoryMutex();
        $signals = new SignalDispatcher();
        $events = [];

        foreach ([
            LifecycleSignals::SCHEDULE_TASK_STARTING,
            LifecycleSignals::SCHEDULE_TASK_FINISHED,
            LifecycleSignals::SCHEDULE_TASK_FAILED,
            LifecycleSignals::SCHEDULE_TASK_SKIPPED,
        ] as $signal) {
            $signals->listen($signal, function (array $payload) use ($signal, &$events): void {
                $events[] = [$signal, $payload['task'], $payload['reason'] ?? null];
            });
        }

        $schedule = new Schedule();
        $task = $schedule->call(static function (): void {
            throw new RuntimeException('boom');
        }, 'guarded-task')->everyMinute()->withoutOverlapping(60);

        $runner = new ScheduleRunner($mutex, $signals);

        // Pre-acquire lock to simulate another worker running the task.
        $this->assertTrue($mutex->acquire($task->lockKey(), 60));
        $skipped = $runner->run($schedule, new DateTimeImmutable('2026-11-27 14:05:00'));
        $this->assertSame(TaskRunResult::STATUS_SKIPPED, $skipped[0]->status);
        $this->assertSame('overlapping', $skipped[0]->reason);

        // Release lock, then run failing task and verify lock is still released in finally.
        $mutex->release($task->lockKey());
        $failed = $runner->run($schedule, new DateTimeImmutable('2026-11-27 14:05:00'));
        $this->assertTrue($failed[0]->failed());
        $this->assertTrue($mutex->acquire($task->lockKey(), 60));
        $mutex->release($task->lockKey());

        $this->assertSame([
            [LifecycleSignals::SCHEDULE_TASK_SKIPPED, 'guarded-task', 'overlapping'],
            [LifecycleSignals::SCHEDULE_TASK_STARTING, 'guarded-task', null],
            [LifecycleSignals::SCHEDULE_TASK_FAILED, 'guarded-task', null],
        ], $events);
    }

    public function test_failure_in_one_task_does_not_stop_subsequent_tasks(): void
    {
        $secondRan = false;
        $schedule = new Schedule();
        $schedule->call(static function (): void {
            throw new RuntimeException('first failed');
        }, 'first')->everyMinute();

        $schedule->call(function () use (&$secondRan): void {
            $secondRan = true;
        }, 'second')->everyMinute();

        $runner = new ScheduleRunner(new InMemoryMutex());
        $results = $runner->run($schedule, new DateTimeImmutable('2026-11-27 14:05:00'));

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->failed());
        $this->assertTrue($results[1]->succeeded());
        $this->assertTrue($secondRan);
    }
}

final class DummyScheduledJob implements JobInterface
{
    public function handle(): void
    {
    }

    public function maxAttempts(): int
    {
        return 1;
    }

    public function retryDelay(): int
    {
        return 0;
    }
}
