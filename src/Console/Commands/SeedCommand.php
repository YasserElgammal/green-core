<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'db:seed',
    description: 'Run database seeders',
)]
class SeedCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'class',
                null,
                InputOption::VALUE_REQUIRED,
                'Run only the specified seeder class (short name, e.g. UserSeeder)',
            )
            ->addOption(
                'fresh',
                null,
                InputOption::VALUE_NONE,
                'Truncate all seeder-declared tables across all seeders before running',
            );
    }

    protected function handle(): int
    {
        $class = $this->option('class');
        $fresh = (bool) $this->option('fresh');

        $runner = $this->getSeederRunner();

        try {
            if ($fresh) {
                $this->warn('Running fresh seed — all seeder-declared tables will be truncated.');
                $this->line('');
                $ran = $runner->fresh();
            } else {
                $ran = $runner->run(is_string($class) ? $class : null);
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if (empty($ran)) {
            $this->info('No seeders found. Add seeder files to database/seeders/.');
            return self::SUCCESS;
        }

        foreach ($ran as $seederClass) {
            $this->info("Seeded:  {$seederClass}");
        }

        $count = count($ran);
        $this->success("Done. {$count} seeder(s) run.");

        return self::SUCCESS;
    }
}
