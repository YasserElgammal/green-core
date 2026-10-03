<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use YasserElgammal\Green\Console\Commands\QueueWorkCommand;
use YasserElgammal\Green\Queue\Contracts\JobInterface;
use YasserElgammal\Green\Queue\Contracts\QueueDriverInterface;
use YasserElgammal\Green\Queue\QueueManager;
use YasserElgammal\Green\Queue\Worker;

final class QueueWorkCommandTest extends TestCase
{
    public function testItProcessesJobsUsingTheSelectedQueueAndConnection(): void
    {
        QueueWorkJob::$handled = 0;
        $driver = new QueueWorkDriver();
        $manager = new QueueManager([
            'default' => 'fake',
            'connections' => ['fake' => ['driver' => 'test']],
        ]);
        $manager->extend('test', fn (): QueueDriverInterface => $driver);
        $driver->jobs[] = [
            'id' => 17,
            'payload' => $manager->encode(new QueueWorkJob()),
            'attempts' => 1,
        ];
        $tester = new CommandTester(new QueueWorkCommand(new Worker($manager)));

        $status = $tester->execute([
            '--queue' => 'emails',
            '--connection' => 'fake',
            '--sleep' => '0',
            '--max-jobs' => '1',
        ]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame(1, QueueWorkJob::$handled);
        self::assertSame(['emails'], $driver->poppedQueues);
        self::assertSame([['emails', 17]], $driver->deleted);
        self::assertStringContainsString('queue [emails]', $tester->getDisplay());
    }

    public function testItRejectsInvalidNumericOptions(): void
    {
        $manager = new QueueManager([]);
        $tester = new CommandTester(new QueueWorkCommand(new Worker($manager)));

        $status = $tester->execute(['--sleep' => '-1']);

        self::assertSame(Command::INVALID, $status);
        self::assertStringContainsString('non-negative integer', $tester->getDisplay());
    }

    public function testItReportsConnectionErrorsWithoutACrash(): void
    {
        $manager = new QueueManager(['connections' => []]);
        $tester = new CommandTester(new QueueWorkCommand(new Worker($manager)));

        $status = $tester->execute(['--connection' => 'missing', '--max-jobs' => '1']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Queue connection [missing] is not defined.', $tester->getDisplay());
    }
}

final class QueueWorkDriver implements QueueDriverInterface
{
    public array $jobs = [];
    public array $poppedQueues = [];
    public array $deleted = [];

    public function push(string $queue, string $payload, int $delay = 0): string|int
    {
        return 1;
    }

    public function pop(string $queue): ?array
    {
        $this->poppedQueues[] = $queue;
        return array_shift($this->jobs);
    }

    public function delete(string $queue, string|int $id): void
    {
        $this->deleted[] = [$queue, $id];
    }

    public function release(string $queue, string|int $id, int $delay = 0): void
    {
    }
}

final class QueueWorkJob implements JobInterface
{
    public static int $handled = 0;

    public function handle(): void
    {
        self::$handled++;
    }

    public function maxAttempts(): int
    {
        return 1;
    }

    public function retryDelay(): int
    {
        return 0;
    }
}
