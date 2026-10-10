<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Providers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YasserElgammal\Green\Application;
use YasserElgammal\Green\Config\Typed\QueueConfig;
use YasserElgammal\Green\Providers\QueueServiceProvider;
use YasserElgammal\Green\Queue\Drivers\SyncDriver;
use YasserElgammal\Green\Queue\QueueManager;
use YasserElgammal\Green\Queue\Worker;

final class QueueServiceProviderTest extends TestCase
{
    public function testItRegistersSharedQueueServicesUsingTypedConfiguration(): void
    {
        $application = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $application->instance(QueueConfig::class, new QueueConfig(
            'sync',
            ['sync' => ['driver' => 'sync']],
        ));

        (new QueueServiceProvider($application))->register();

        $manager = $application->make(QueueManager::class);
        self::assertSame($manager, $application->make(QueueManager::class));
        self::assertInstanceOf(SyncDriver::class, $manager->connection());

        $worker = $application->make(Worker::class);
        self::assertSame($worker, $application->make(Worker::class));
    }
}
