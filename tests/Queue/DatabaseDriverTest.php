<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Queue;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Queue\Drivers\DatabaseDriver;

final class DatabaseDriverTest extends TestCase
{
    private Connection $connection;
    private DatabaseDriver $driver;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE queue_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, queue VARCHAR(255), payload TEXT, attempts INTEGER, available_at DATETIME, reserved_at DATETIME NULL, created_at DATETIME)'
        );
        $this->driver = new DatabaseDriver($this->connection, 'queue_jobs');
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    public function testPushAndPopReserveTheOldestAvailableJob(): void
    {
        $firstId = $this->driver->push('emails', 'first');
        $this->driver->push('emails', 'second');
        $this->driver->push('other', 'other');

        $job = $this->driver->pop('emails');

        self::assertSame($firstId, (int) $job['id']);
        self::assertSame('first', $job['payload']);
        self::assertSame(1, $job['attempts']);
        self::assertNull($this->driver->pop('missing'));
        self::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT attempts FROM queue_jobs WHERE id = ?', [$firstId]
        ));
    }

    public function testDelayedAndReservedJobsAreNotAvailable(): void
    {
        $this->driver->push('default', 'delayed', 3600);
        $id = $this->driver->push('default', 'reserved');
        self::assertNotNull($this->driver->pop('default'));

        self::assertNull($this->driver->pop('default'));
        self::assertNotNull($this->connection->fetchOne(
            'SELECT reserved_at FROM queue_jobs WHERE id = ?', [$id]
        ));
    }

    public function testReleaseMakesAJobAvailableAgainAndDeleteRemovesIt(): void
    {
        $id = $this->driver->push('default', 'payload');
        self::assertNotNull($this->driver->pop('default'));

        $this->driver->release('default', $id);
        $retried = $this->driver->pop('default');

        self::assertSame(2, $retried['attempts']);
        $this->driver->delete('default', $id);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM queue_jobs'));
    }
}
