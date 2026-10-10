<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Config;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Config\Repository;
use YasserElgammal\Green\Config\Typed\QueueConfig;

final class QueueConfigTest extends TestCase
{
    public function testItUsesSafeDefaultsWhenQueueConfigurationIsMissing(): void
    {
        $config = QueueConfig::fromRepository(new Repository());

        self::assertSame('sync', $config->default);
        self::assertSame([], $config->connections);
    }

    public function testItBuildsAnImmutableSnapshotFromTheRepository(): void
    {
        $connections = [
            'database' => ['driver' => 'database', 'table' => 'jobs'],
        ];

        $config = QueueConfig::fromRepository(new Repository([
            'queue' => ['default' => 'database', 'connections' => $connections],
        ]));

        self::assertSame('database', $config->default);
        self::assertSame($connections, $config->connections);
        self::assertSame([
            'default' => 'database',
            'connections' => $connections,
        ], $config->toArray());
    }
}
