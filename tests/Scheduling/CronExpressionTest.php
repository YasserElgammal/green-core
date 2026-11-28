<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Scheduling;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Scheduling\CronExpression;
use YasserElgammal\Green\Scheduling\Exceptions\InvalidCronExpressionException;

final class CronExpressionTest extends TestCase
{
    public function test_wildcard_matches_every_minute(): void
    {
        $cron = new CronExpression('* * * * *');

        $this->assertTrue($cron->isDue(new DateTimeImmutable('2026-11-27 14:05:00')));
        $this->assertTrue($cron->isDue(new DateTimeImmutable('2026-01-01 00:00:00')));
    }

    public function test_steps_ranges_and_lists_are_evaluated(): void
    {
        // Every 15 minutes during hours 9-17 on weekdays (Mon-Fri)
        $cron = new CronExpression('*/15 9-17 * * 1-5');

        $this->assertTrue($cron->isDue(new DateTimeImmutable('2026-11-27 14:30:00'))); // Friday
        $this->assertFalse($cron->isDue(new DateTimeImmutable('2026-11-27 14:31:00')));
        $this->assertFalse($cron->isDue(new DateTimeImmutable('2026-11-27 18:00:00')));
        $this->assertFalse($cron->isDue(new DateTimeImmutable('2026-11-28 10:00:00'))); // Saturday

        $listCron = new CronExpression('5,20,45 8,20 1,15 11 *');
        $this->assertTrue($listCron->isDue(new DateTimeImmutable('2026-11-15 20:20:00')));
        $this->assertFalse($listCron->isDue(new DateTimeImmutable('2026-11-15 20:21:00')));
    }

    public function test_sunday_accepts_both_zero_and_seven(): void
    {
        $sundayZero = new CronExpression('0 12 * * 0');
        $sundaySeven = new CronExpression('0 12 * * 7');
        $sunday = new DateTimeImmutable('2026-11-29 12:00:00'); // Sunday

        $this->assertTrue($sundayZero->isDue($sunday));
        $this->assertTrue($sundaySeven->isDue($sunday));
    }

    public function test_next_run_date_computes_upcoming_match(): void
    {
        $cron = new CronExpression('30 14 * * *');

        $before = new DateTimeImmutable('2026-11-27 14:05:00');
        $after = new DateTimeImmutable('2026-11-27 14:30:00');

        $this->assertSame('2026-11-27 14:30:00', $cron->nextRunDate($before)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-28 14:30:00', $cron->nextRunDate($after)->format('Y-m-d H:i:s'));
    }

    public function test_invalid_expressions_throw_exception(): void
    {
        $this->expectException(InvalidCronExpressionException::class);
        new CronExpression('* * *');
    }

    public function test_out_of_bounds_field_throws_exception(): void
    {
        $this->expectException(InvalidCronExpressionException::class);
        new CronExpression('65 * * * *');
    }
}
