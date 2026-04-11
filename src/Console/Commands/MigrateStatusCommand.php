<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use YasserElgammal\Green\Database\Migrations\MigrationRunner;

#[AsCommand(
    name: 'migrate:status',
    description: 'Show the status of each migration (ran / pending)',
)]
class MigrateStatusCommand extends BaseCommand
{
    // constructor removed to rely on BaseCommand's getMigrationRunner()

    protected function handle(): int
    {
        try {
            $runner = $this->getMigrationRunner();
            $status = $runner->status();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if (empty($status)) {
            $this->info('No migration files found.');
            return self::SUCCESS;
        }

        // Build a Symfony Console table
        $rows = [];
        foreach ($status as $migration => $state) {
            $rows[] = [
                $migration,
                $state === 'ran'
                    ? '<info>✔ Ran</info>'
                    : '<comment>⏳ Pending</comment>',
            ];
        }

        $this->io->table(['Migration', 'Status'], $rows);

        return self::SUCCESS;
    }
}
