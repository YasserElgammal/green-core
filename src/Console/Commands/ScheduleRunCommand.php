<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Console\Commands;

use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use YasserElgammal\Green\Scheduling\Schedule;
use YasserElgammal\Green\Scheduling\ScheduleRunner;

#[AsCommand(
    name: 'schedule:run',
    description: 'Run due scheduled tasks',
)]
final class ScheduleRunCommand extends BaseCommand
{
    public function __construct(
        private readonly Schedule $schedule,
        private readonly ScheduleRunner $runner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'task',
            null,
            InputOption::VALUE_OPTIONAL,
            'Run a single scheduled task by name',
        );
    }

    protected function handle(): int
    {
        $taskOption = $this->option('task');
        $taskName = is_string($taskOption) && trim($taskOption) !== '' ? trim($taskOption) : null;

        $runner = $this->runner;
        $consoleApp = $this->getApplication();

        if ($consoleApp !== null) {
            $runner = $runner->withCommandRunner(
                function (string $command, array $arguments) use ($consoleApp): int {
                    $target = $consoleApp->find($command);
                    $input = new ArrayInput(array_merge(['command' => $command], $arguments));
                    $input->setInteractive(false);

                    return $target->run($input, $this->output);
                }
            );
        }

        $results = $runner->run($this->schedule, new DateTimeImmutable(), $taskName);

        if ($results === []) {
            $this->info($taskName !== null
                ? "No scheduled task named [{$taskName}] was found."
                : 'No scheduled tasks are due to run.');

            return self::SUCCESS;
        }

        $hasFailures = false;

        foreach ($results as $result) {
            $name = $result->task->nameValue();

            if ($result->succeeded()) {
                $this->info(sprintf('Ran [%s] in %.2fms.', $name, $result->durationMs));
            } elseif ($result->skipped()) {
                $this->warn(sprintf('Skipped [%s] (%s).', $name, $result->reason ?? 'skipped'));
            } else {
                $hasFailures = true;
                $this->error(sprintf('Failed [%s]: %s', $name, $result->reason ?? 'unknown error'));
            }
        }

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }
}
