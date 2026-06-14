<?php

namespace YasserElgammal\Green\Console;

use YasserElgammal\Green\Console\Commands\{
    ServeCommand,
    CreateControllerCommand,
    CreateMigrationCommand,
    CreateModelCommand,
    CreateProviderCommand,
    MigrateCommand,
    MigrateRollbackCommand,
    MigrateStatusCommand,
    RouteCacheCommand,
    RouteClearCommand,
    PublishConfigCommand,
    TranslationClearCommand,
};

class Kernel
{
    protected array $commands = [];

    protected array $coreCommands = [
        ServeCommand::class,
        CreateControllerCommand::class,
        CreateModelCommand::class,
        CreateProviderCommand::class,
        CreateMigrationCommand::class,
        MigrateCommand::class,
        MigrateRollbackCommand::class,
        MigrateStatusCommand::class,
        RouteCacheCommand::class,
        RouteClearCommand::class,
        PublishConfigCommand::class,
        TranslationClearCommand::class,
    ];

    protected \YasserElgammal\Green\Application $app;

    public function handle(): void
    {
        // Boot the main application (loads config, registers providers)
        $this->app = new \YasserElgammal\Green\Application();

        $consoleApp = new Application();

        foreach ($this->coreCommands as $command) {
            $consoleApp->addCommand($this->app->make($command));
        }

        foreach ($this->commands as $command) {
            $consoleApp->addCommand($this->app->make($command));
        }

        $consoleApp->run();
    }
}