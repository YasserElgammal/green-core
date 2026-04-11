<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use YasserElgammal\Green\Database\Migrations\MigrationRunner;
use YasserElgammal\Green\Database\Schema\Schema;

#[AsCommand(
    name: 'migrate:rollback',
    description: 'Roll back the last batch of migrations',
)]
class MigrateRollbackCommand extends BaseCommand
{
    // constructor removed to rely on BaseCommand's getMigrationRunner()

    protected function configure(): void
    {
        $this
            ->addOption('dry',   null, InputOption::VALUE_NONE, 'Print SQL without executing')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Allow destructive operations (disables safe mode)');
    }

    protected function handle(): int
    {
        $dry   = (bool) $this->option('dry');
        $force = (bool) $this->option('force');

        Schema::setSafeMode(!$force);
        
        $runner = $this->getMigrationRunner();
        $runner->setDryRun($dry);

        if ($dry) {
            $this->warn('DRY RUN — no SQL will be executed.');
            $this->line('');
        }

        try {
            $rolled = $runner->rollback();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if (empty($rolled)) {
            $this->info('Nothing to roll back.');
            return self::SUCCESS;
        }

        if ($dry) {
            $this->line('<fg=magenta>SQL that would be executed:</>');
            foreach ($runner->getDryRunOutput() as $sql) {
                $this->line("  <fg=yellow>{$sql};</>");
            }
            $this->line('');
        }

        foreach ($rolled as $migration) {
            $this->info("Rolled back:  {$migration}");
        }

        $count = count($rolled);
        $this->success("Done. {$count} migration(s) rolled back.");

        return self::SUCCESS;
    }
}
