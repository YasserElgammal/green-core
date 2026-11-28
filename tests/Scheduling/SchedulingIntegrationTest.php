<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Scheduling;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use YasserElgammal\Green\Application;
use YasserElgammal\Green\Cache\Drivers\ArrayDriver;
use YasserElgammal\Green\Console\Application as ConsoleApplication;
use YasserElgammal\Green\Console\Commands\ScheduleListCommand;
use YasserElgammal\Green\Console\Commands\ScheduleRunCommand;
use YasserElgammal\Green\Scheduling\Contracts\MutexInterface;
use YasserElgammal\Green\Scheduling\Mutex\CacheMutex;
use YasserElgammal\Green\Scheduling\Schedule;
use YasserElgammal\Green\Scheduling\ScheduleRunner;
use YasserElgammal\Green\Signal\SignalDispatcher;

final class SchedulingIntegrationTest extends TestCase
{
    private bool $bootedApplication = false;

    protected function tearDown(): void
    {
        if ($this->bootedApplication) {
            restore_error_handler();
            restore_exception_handler();
            unset($GLOBALS['__green_app']);
            $this->bootedApplication = false;
        }

        parent::tearDown();
    }

    public function test_provider_registers_singletons_in_application(): void
    {
        $this->bootedApplication = true;
        $app = new Application([
            'app' => ['env' => 'testing'],
        ], dirname(__DIR__, 2));

        $scheduleA = $app->make(Schedule::class);
        $scheduleB = $app->make(Schedule::class);

        $this->assertSame($scheduleA, $scheduleB);
        $this->assertInstanceOf(MutexInterface::class, $app->make(MutexInterface::class));
        $this->assertInstanceOf(ScheduleRunner::class, $app->make(ScheduleRunner::class));
    }

    public function test_cache_mutex_acquires_and_releases_locks(): void
    {
        $mutex = new CacheMutex(new ArrayDriver());

        $this->assertTrue($mutex->acquire('task-a', 30));
        $this->assertFalse($mutex->acquire('task-a', 30));

        $mutex->release('task-a');

        $this->assertTrue($mutex->acquire('task-a', 30));
    }

    public function test_console_commands_run_and_list_scheduled_tasks(): void
    {
        $this->bootedApplication = true;
        $app = new Application([
            'app' => ['env' => 'testing'],
        ], dirname(__DIR__, 2));

        $executed = false;
        /** @var Schedule $schedule */
        $schedule = $app->make(Schedule::class);
        $schedule->call(function () use (&$executed): void {
            $executed = true;
        }, 'heartbeat')
            ->everyMinute()
            ->description('Heartbeat check');

        $consoleApp = new ConsoleApplication($app->make(SignalDispatcher::class));
        $consoleApp->addCommand($app->make(ScheduleRunCommand::class));
        $consoleApp->addCommand($app->make(ScheduleListCommand::class));

        $listTester = new CommandTester($consoleApp->find('schedule:list'));
        $listExit = $listTester->execute([]);

        $this->assertSame(Command::SUCCESS, $listExit);
        $this->assertStringContainsString('heartbeat', $listTester->getDisplay());
        $this->assertStringContainsString('Heartbeat check', $listTester->getDisplay());

        $runTester = new CommandTester($consoleApp->find('schedule:run'));
        $runExit = $runTester->execute(['--task' => 'heartbeat']);

        $this->assertSame(Command::SUCCESS, $runExit);
        $this->assertTrue($executed);
        $this->assertStringContainsString('Ran [heartbeat]', $runTester->getDisplay());
    }
}
