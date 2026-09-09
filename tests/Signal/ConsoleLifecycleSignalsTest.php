<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Signal;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use YasserElgammal\Green\Console\Application;
use YasserElgammal\Green\Signal\LifecycleSignals;
use YasserElgammal\Green\Signal\SignalDispatcher;

final class ConsoleLifecycleSignalsTest extends TestCase
{
    public function test_it_emits_starting_and_finished_around_a_successful_command(): void
    {
        $signals = new SignalDispatcher();
        $events = [];
        $signals->listen(LifecycleSignals::COMMAND_STARTING, function (array $payload) use (&$events): void {
            $events[] = [LifecycleSignals::COMMAND_STARTING, $payload];
        });
        $signals->listen(LifecycleSignals::COMMAND_FINISHED, function (array $payload) use (&$events): void {
            $events[] = [LifecycleSignals::COMMAND_FINISHED, $payload];
        });
        $application = $this->application($signals, new class extends Command {
            public function __construct()
            {
                parent::__construct('test:success');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return Command::SUCCESS;
            }
        });

        $exitCode = $application->run(new ArrayInput(['command' => 'test:success']), new BufferedOutput());

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(LifecycleSignals::COMMAND_STARTING, $events[0][0]);
        self::assertSame('test:success', $events[0][1]['command']);
        self::assertSame(LifecycleSignals::COMMAND_FINISHED, $events[1][0]);
        self::assertSame(Command::SUCCESS, $events[1][1]['exit_code']);
        self::assertIsFloat($events[1][1]['duration_ms']);
    }

    public function test_it_emits_finished_when_a_command_throws(): void
    {
        $signals = new SignalDispatcher();
        $finished = null;
        $signals->listen(
            LifecycleSignals::COMMAND_FINISHED,
            function (array $payload) use (&$finished): void {
                $finished = $payload;
            },
        );
        $application = $this->application($signals, new class extends Command {
            public function __construct()
            {
                parent::__construct('test:failure');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                throw new RuntimeException('command failed');
            }
        });

        self::assertSame(
            Command::FAILURE,
            $application->run(new ArrayInput(['command' => 'test:failure']), new BufferedOutput()),
        );
        self::assertSame('test:failure', $finished['command']);
        self::assertSame(Command::FAILURE, $finished['exit_code']);
        self::assertInstanceOf(RuntimeException::class, $finished['exception']);
    }

    private function application(SignalDispatcher $signals, Command $command): Application
    {
        $application = new Application($signals);
        $application->setAutoExit(false);
        $application->addCommand($command);

        return $application;
    }
}
