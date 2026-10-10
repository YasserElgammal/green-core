<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Queue;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Queue\Drivers\SyncDriver;
use YasserElgammal\Green\Queue\QueueManager;

final class SyncDriverTest extends TestCase
{
    public function testPushExecutesTheJobImmediatelyAndReturnsAnIdentifier(): void
    {
        SyncExecutedJob::$handled = 0;
        $payload = (new QueueManager([]))->encode(new SyncExecutedJob());

        $id = (new SyncDriver())->push('default', $payload, 30);

        self::assertSame(1, SyncExecutedJob::$handled);
        self::assertStringStartsWith('sync_', (string) $id);
    }

    public function testItNeverExposesQueuedJobs(): void
    {
        $driver = new SyncDriver();

        self::assertNull($driver->pop('default'));
        $driver->delete('default', 'ignored');
        $driver->release('default', 'ignored', 10);

        self::assertNull($driver->pop('default'));
    }
}

final class SyncExecutedJob implements \YasserElgammal\Green\Queue\Contracts\JobInterface
{
    public static int $handled = 0;
    public function handle(): void { self::$handled++; }
    public function maxAttempts(): int { return 1; }
    public function retryDelay(): int { return 0; }
}
