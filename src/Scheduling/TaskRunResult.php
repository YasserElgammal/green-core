<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Scheduling;

use Throwable;

final class TaskRunResult
{
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        public readonly ScheduledTask $task,
        public readonly string $status,
        public readonly float $durationMs,
        public readonly ?string $reason = null,
        public readonly ?Throwable $exception = null,
    ) {
    }

    public function succeeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    public function skipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    public function failed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
