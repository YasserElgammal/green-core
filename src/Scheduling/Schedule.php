<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Scheduling;

use YasserElgammal\Green\Queue\Contracts\JobInterface;

final class Schedule
{
    /** @var list<ScheduledTask> */
    private array $tasks = [];

    public function call(callable $callback, ?string $name = null): ScheduledTask
    {
        $task = ScheduledTask::forCallback($callback, $name);
        $this->tasks[] = $task;

        return $task;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function command(string $command, array $arguments = []): ScheduledTask
    {
        $task = ScheduledTask::forCommand($command, $arguments);
        $this->tasks[] = $task;

        return $task;
    }

    public function job(
        JobInterface|string $job,
        string $queue = 'default',
        ?string $connection = null,
    ): ScheduledTask {
        $task = ScheduledTask::forJob($job, $queue, $connection);
        $this->tasks[] = $task;

        return $task;
    }

    /**
     * @return list<ScheduledTask>
     */
    public function tasks(): array
    {
        return $this->tasks;
    }
}
