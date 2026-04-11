<?php

namespace YasserElgammal\Green\Console;

use YasserElgammal\Green\Console\Commands\ServeCommand;
use YasserElgammal\Green\Console\Commands\CreateControllerCommand;
use YasserElgammal\Green\Console\Commands\CreateMigrationCommand;
use YasserElgammal\Green\Console\Commands\CreateModelCommand;
use YasserElgammal\Green\Console\Commands\MigrateCommand;
use YasserElgammal\Green\Console\Commands\MigrateRollbackCommand;
use YasserElgammal\Green\Console\Commands\MigrateStatusCommand;

class Kernel
{
    protected array $commands = [];

    protected array $coreCommands = [
        ServeCommand::class,
        CreateControllerCommand::class,
        CreateModelCommand::class,
        CreateMigrationCommand::class,
        MigrateCommand::class,
        MigrateRollbackCommand::class,
        MigrateStatusCommand::class,
    ];

    public function handle(): void
    {
        $app = new Application();

        foreach ($this->coreCommands as $command) {
            $app->addCommand(new $command());
        }

        foreach ($this->commands as $command) {
            $app->addCommand(new $command());
        }

        $app->run();
    }
}
