<?php

namespace YasserElgammal\Green\Console;

use YasserElgammal\Green\Console\Commands\{
    ServeCommand,
    CreateControllerCommand,
    CreateMigrationCommand,
    CreateModelCommand,
    MigrateCommand,
    MigrateRollbackCommand,
    MigrateStatusCommand,
    TranslationClearCommand,
};

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
        TranslationClearCommand::class,
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
