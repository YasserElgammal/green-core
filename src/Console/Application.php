<?php

namespace YasserElgammal\Green\Console;

use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YasserElgammal\Green\Signal\LifecycleSignals;
use YasserElgammal\Green\Signal\SignalDispatcher;

class Application extends SymfonyApplication
{
    public function __construct(private readonly ?SignalDispatcher $signals = null)
    {
        parent::__construct('Green Framework', '2.0.0');
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        $startedAt = hrtime(true);
        $commandName = $command->getName() ?? $command::class;

        try {
            $this->signals?->emit(LifecycleSignals::COMMAND_STARTING, [
                'command' => $commandName,
                'input' => $input,
            ]);

            $exitCode = parent::doRunCommand($command, $input, $output);
        } catch (\Throwable $e) {
            $failureCode = $e->getCode() > 0 ? $e->getCode() : Command::FAILURE;
            $this->emitFinishedSafely($commandName, $input, $failureCode, $startedAt, $e);
            throw $e;
        }

        $this->emitFinishedSafely($commandName, $input, $exitCode, $startedAt);

        return $exitCode;
    }

    private function emitFinishedSafely(
        string $commandName,
        InputInterface $input,
        int $exitCode,
        int $startedAt,
        ?\Throwable $exception = null,
    ): void {
        if ($this->signals === null) {
            return;
        }

        $payload = [
            'command' => $commandName,
            'input' => $input,
            'exit_code' => $exitCode,
            'duration_ms' => (hrtime(true) - $startedAt) / 1_000_000,
        ];
        if ($exception !== null) {
            $payload['exception'] = $exception;
        }

        try {
            $this->signals->emit(LifecycleSignals::COMMAND_FINISHED, $payload);
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[Green] Signal [%s] listener failed: %s',
                LifecycleSignals::COMMAND_FINISHED,
                $e->getMessage(),
            ));
        }
    }
}
