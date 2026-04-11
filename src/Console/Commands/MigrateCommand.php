<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use YasserElgammal\Green\Database\Migrations\MigrationRunner;
use YasserElgammal\Green\Database\Schema\Schema;

#[AsCommand(
    name: 'migrate',
    description: 'Run all pending database migrations',
)]
class MigrateCommand extends BaseCommand
{
    // constructor removed to rely on BaseCommand's getMigrationRunner()

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::OPTIONAL, 'Specific action like "fresh"')
            ->addOption('dry',   null, InputOption::VALUE_NONE, 'Print SQL without executing')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Allow destructive operations (disables safe mode)');
    }

    protected function handle(): int
    {
        $action = $this->argument('action');
        $dry   = (bool) $this->option('dry');
        $force = (bool) $this->option('force');

        // Safe mode ON by default; --force disables it
        Schema::setSafeMode(!$force);
        
        $runner = $this->getMigrationRunner();
        $runner->setDryRun($dry);

        if ($dry) {
            $this->warn('DRY RUN — no SQL will be executed.');
            $this->line('');
        }

        if ($action === 'fresh') {
            $this->warn('Dropping all tables...');
            if (!$dry) {
                Schema::dropAllTables();
            }
            $this->info('All tables dropped successfully.');
        } elseif ($action !== null) {
            $this->error("Unknown action: {$action}. Use 'fresh' or leave empty.");
            return self::FAILURE;
        }

        try {
            $ran = $runner->run();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if (empty($ran)) {
            $this->info('Nothing to migrate. Everything is up to date.');
            return self::SUCCESS;
        }

        if ($dry) {
            $this->line('<fg=magenta>SQL that would be executed:</>');
            foreach ($runner->getDryRunOutput() as $sql) {
                $this->line("  <fg=yellow>{$sql};</>");
            }
            $this->line('');
        }

        foreach ($ran as $migration) {
            $this->info("Migrated:  {$migration}");
        }

        $count = count($ran);
        $this->success("Done. {$count} migration(s) run.");

        return self::SUCCESS;
    }
}