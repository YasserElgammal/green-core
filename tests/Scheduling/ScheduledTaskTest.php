<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Scheduling;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Scheduling\ScheduledTask;

final class ScheduledTaskTest extends TestCase
{
    public function test_frequency_helpers_set_expected_cron_expressions(): void
    {
        $task = ScheduledTask::forCallback(static fn () => null);

        $this->assertSame('* * * * *', $task->everyMinute()->expression());
        $this->assertSame('*/5 * * * *', $task->everyFiveMinutes()->expression());
        $this->assertSame('0 * * * *', $task->hourly()->expression());
        $this->assertSame('15 * * * *', $task->hourlyAt(15)->expression());
        $this->assertSame('0 0 * * *', $task->daily()->expression());
        $this->assertSame('30 9 * * *', $task->dailyAt('09:30')->expression());
        $this->assertSame('0 0 * * 0', $task->weekly()->expression());
        $this->assertSame('0 0 1 * *', $task->monthly()->expression());
    }

    public function test_timezone_is_applied_during_due_evaluation(): void
    {
        $task = ScheduledTask::forCallback(static fn () => null)
            ->dailyAt('14:00')
            ->timezone('Africa/Cairo');

        // 12:00 UTC == 14:00 Africa/Cairo (+02:00)
        $utcNow = new DateTimeImmutable('2026-11-27 12:00:00', new \DateTimeZone('UTC'));

        $this->assertTrue($task->isDue($utcNow));
    }

    public function test_when_and_skip_predicates_control_should_run(): void
    {
        $allowed = ScheduledTask::forCallback(static fn () => null)
            ->when(static fn (): bool => true)
            ->skip(static fn (): bool => false);

        $blockedByWhen = ScheduledTask::forCallback(static fn () => null)
            ->when(static fn (): bool => false);

        $blockedBySkip = ScheduledTask::forCallback(static fn () => null)
            ->when(static fn (): bool => true)
            ->skip(static fn (): bool => true);

        $this->assertTrue($allowed->shouldRun());
        $this->assertFalse($blockedByWhen->shouldRun());
        $this->assertFalse($blockedBySkip->shouldRun());
    }

    public function test_invalid_daily_at_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ScheduledTask::forCallback(static fn () => null)->dailyAt('25:99');
    }
}
