<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Signal;

final class LifecycleSignals
{
    public const REQUEST_RECEIVED = 'request.received';
    public const REQUEST_HANDLED = 'request.handled';
    public const EXCEPTION_OCCURRED = 'exception.occurred';
    public const COMMAND_STARTING = 'command.starting';
    public const COMMAND_FINISHED = 'command.finished';
    public const SCHEDULE_TASK_STARTING = 'schedule.task.starting';
    public const SCHEDULE_TASK_FINISHED = 'schedule.task.finished';
    public const SCHEDULE_TASK_FAILED = 'schedule.task.failed';
    public const SCHEDULE_TASK_SKIPPED = 'schedule.task.skipped';

    private function __construct()
    {
    }
}
