<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Scheduling;

use Closure;
use DateTimeImmutable;
use RuntimeException;
use Throwable;
use YasserElgammal\Green\Queue\Contracts\JobInterface;
use YasserElgammal\Green\Queue\QueueManager;
use YasserElgammal\Green\Scheduling\Contracts\MutexInterface;
use YasserElgammal\Green\Signal\LifecycleSignals;
use YasserElgammal\Green\Signal\SignalDispatcher;

final class ScheduleRunner
{
    private ?Closure $commandRunner;
    private ?Closure $jobResolver;

    /**
     * @param callable(string, array<string, mixed>): int|null $commandRunner
     * @param callable(string): object|null $jobResolver
     */
    public function __construct(
        private readonly MutexInterface $mutex,
        private readonly ?SignalDispatcher $signals = null,
        private readonly ?QueueManager $queue = null,
        ?callable $commandRunner = null,
        ?callable $jobResolver = null,
    ) {
        $this->commandRunner = $commandRunner !== null ? Closure::fromCallable($commandRunner) : null;
        $this->jobResolver = $jobResolver !== null ? Closure::fromCallable($jobResolver) : null;
    }

    /**
     * @param callable(string, array<string, mixed>): int $commandRunner
     */
    public function withCommandRunner(callable $commandRunner): self
    {
        return new self(
            mutex: $this->mutex,
            signals: $this->signals,
            queue: $this->queue,
            commandRunner: $commandRunner,
            jobResolver: $this->jobResolver,
        );
    }

    /**
     * Run all due tasks (or a single named task when $taskName is provided).
     *
     * @return list<TaskRunResult>
     */
    public function run(
        Schedule $schedule,
        ?DateTimeImmutable $now = null,
        ?string $taskName = null,
    ): array {
        $now ??= new DateTimeImmutable();
        $results = [];

        foreach ($schedule->tasks() as $task) {
            if ($taskName !== null && $task->nameValue() !== $taskName) {
                continue;
            }

            if ($taskName === null && !$task->isDue($now)) {
                continue;
            }

            $results[] = $this->runTask($task);
        }

        return $results;
    }

    private function runTask(ScheduledTask $task): TaskRunResult
    {
        if (!$task->shouldRun()) {
            $this->emitSafely(LifecycleSignals::SCHEDULE_TASK_SKIPPED, [
                'task' => $task->nameValue(),
                'type' => $task->type(),
                'reason' => 'predicate',
            ]);

            return new TaskRunResult($task, TaskRunResult::STATUS_SKIPPED, 0.0, 'predicate');
        }

        $lockAcquired = false;
        if ($task->preventsOverlapping()) {
            $lockAcquired = $this->mutex->acquire(
                $task->lockKey(),
                $task->overlapExpiresAfterSeconds(),
            );

            if (!$lockAcquired) {
                $this->emitSafely(LifecycleSignals::SCHEDULE_TASK_SKIPPED, [
                    'task' => $task->nameValue(),
                    'type' => $task->type(),
                    'reason' => 'overlapping',
                ]);

                return new TaskRunResult($task, TaskRunResult::STATUS_SKIPPED, 0.0, 'overlapping');
            }
        }

        $startedAt = hrtime(true);

        $this->emitSafely(LifecycleSignals::SCHEDULE_TASK_STARTING, [
            'task' => $task->nameValue(),
            'type' => $task->type(),
        ]);

        try {
            $this->executeTask($task);
            $durationMs = (hrtime(true) - $startedAt) / 1_000_000;

            $this->emitSafely(LifecycleSignals::SCHEDULE_TASK_FINISHED, [
                'task' => $task->nameValue(),
                'type' => $task->type(),
                'duration_ms' => $durationMs,
            ]);

            return new TaskRunResult($task, TaskRunResult::STATUS_SUCCEEDED, $durationMs);
        } catch (Throwable $exception) {
            $durationMs = (hrtime(true) - $startedAt) / 1_000_000;

            $this->emitSafely(LifecycleSignals::SCHEDULE_TASK_FAILED, [
                'task' => $task->nameValue(),
                'type' => $task->type(),
                'duration_ms' => $durationMs,
                'exception' => $exception,
            ]);

            return new TaskRunResult(
                $task,
                TaskRunResult::STATUS_FAILED,
                $durationMs,
                $exception->getMessage(),
                $exception,
            );
        } finally {
            if ($lockAcquired) {
                $this->mutex->release($task->lockKey());
            }
        }
    }

    private function executeTask(ScheduledTask $task): void
    {
        match ($task->type()) {
            ScheduledTask::TYPE_CALLBACK => $this->executeCallback($task),
            ScheduledTask::TYPE_COMMAND => $this->executeCommand($task),
            ScheduledTask::TYPE_JOB => $this->executeJob($task),
            default => throw new RuntimeException("Unsupported scheduled task type [{$task->type()}]."),
        };
    }

    private function executeCallback(ScheduledTask $task): void
    {
        $callback = $task->callback();
        if ($callback === null) {
            throw new RuntimeException('Scheduled callback task has no callable.');
        }

        $callback();
    }

    private function executeCommand(ScheduledTask $task): void
    {
        if ($this->commandRunner === null) {
            throw new RuntimeException(
                "No command runner configured for scheduled command [{$task->command()}]."
            );
        }

        $exitCode = ($this->commandRunner)((string) $task->command(), $task->commandArguments());
        if ($exitCode !== 0) {
            throw new RuntimeException(
                "Scheduled command [{$task->command()}] exited with code {$exitCode}."
            );
        }
    }

    private function executeJob(ScheduledTask $task): void
    {
        if ($this->queue === null) {
            throw new RuntimeException('QueueManager is required to dispatch scheduled jobs.');
        }

        $job = $task->job();
        if (is_string($job)) {
            $job = $this->jobResolver !== null ? ($this->jobResolver)($job) : new $job();
        }

        if (!$job instanceof JobInterface) {
            throw new RuntimeException('Scheduled job must implement JobInterface.');
        }

        $this->queue->dispatch($job, $task->queue(), $task->queueConnection());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function emitSafely(string $signal, array $payload): void
    {
        if ($this->signals === null) {
            return;
        }

        try {
            $this->signals->emit($signal, $payload);
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[Green] Signal [%s] listener failed: %s',
                $signal,
                $exception->getMessage(),
            ));
        }
    }
}
