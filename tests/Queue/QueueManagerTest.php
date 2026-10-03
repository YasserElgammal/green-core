<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Queue;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use YasserElgammal\Green\Queue\Contracts\JobInterface;
use YasserElgammal\Green\Queue\Contracts\QueueDriverInterface;
use YasserElgammal\Green\Queue\Drivers\SyncDriver;
use YasserElgammal\Green\Queue\QueueManager;

final class QueueManagerTest extends TestCase
{
    public function testItResolvesAndCachesTheDefaultConnection(): void
    {
        $manager = new QueueManager([
            'default' => 'sync',
            'connections' => ['sync' => ['driver' => 'sync']],
        ]);

        $first = $manager->connection();

        self::assertInstanceOf(SyncDriver::class, $first);
        self::assertSame($first, $manager->connection('sync'));
    }

    public function testItUsesACustomDriverCreatorAndDispatchesEncodedJob(): void
    {
        $driver = new RecordingQueueDriver();
        $manager = new QueueManager([
            'default' => 'custom',
            'connections' => ['custom' => ['driver' => 'recording', 'option' => 'value']],
        ]);
        $receivedConfig = null;
        $manager->extend('recording', function (array $config) use ($driver, &$receivedConfig): QueueDriverInterface {
            $receivedConfig = $config;
            return $driver;
        });

        $id = $manager->dispatch(new SerializableQueueJob('hello'), 'emails');

        self::assertSame(41, $id);
        self::assertSame(['driver' => 'recording', 'option' => 'value'], $receivedConfig);
        self::assertSame('emails', $driver->pushedQueue);
        self::assertSame('hello', $manager->decode($driver->pushedPayload)->value);
    }

    public function testItRejectsUndefinedConnections(): void
    {
        $manager = new QueueManager(['connections' => []]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Queue connection [missing] is not defined.');

        $manager->connection('missing');
    }

    public function testItRejectsUnsupportedDrivers(): void
    {
        $manager = new QueueManager([
            'connections' => ['invalid' => ['driver' => 'unknown']],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Queue driver [unknown] is not supported.');

        $manager->connection('invalid');
    }
}

final class RecordingQueueDriver implements QueueDriverInterface
{
    public ?string $pushedQueue = null;
    public string $pushedPayload = '';

    public function push(string $queue, string $payload, int $delay = 0): string|int
    {
        $this->pushedQueue = $queue;
        $this->pushedPayload = $payload;
        return 41;
    }

    public function pop(string $queue): ?array { return null; }
    public function delete(string $queue, string|int $id): void {}
    public function release(string $queue, string|int $id, int $delay = 0): void {}
}

final class SerializableQueueJob implements JobInterface
{
    public function __construct(public readonly string $value) {}
    public function handle(): void {}
    public function maxAttempts(): int { return 3; }
    public function retryDelay(): int { return 5; }
}
