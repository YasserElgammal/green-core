<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Console\Commands;

use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use YasserElgammal\Green\Scheduling\Schedule;

#[AsCommand(
    name: 'schedule:list',
    description: 'List all scheduled tasks',
)]
final class ScheduleListCommand extends BaseCommand
{
    public function __construct(private readonly Schedule $schedule)
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        $tasks = $this->schedule->tasks();

        if ($tasks === []) {
            $this->info('No scheduled tasks registered.');
            return self::SUCCESS;
        }

        $now = new DateTimeImmutable();
        $rows = [];

        foreach ($tasks as $task) {
            $rows[] = [
                $task->nameValue(),
                $task->type(),
                $task->expression(),
                $task->timezoneName(),
                $task->nextRunDate($now)->format('Y-m-d H:i:s T'),
                $task->descriptionValue() ?? '',
            ];
        }

        $this->io->table(
            ['Name', 'Type', 'Expression', 'Timezone', 'Next Due', 'Description'],
            $rows,
        );

        return self::SUCCESS;
    }
}
