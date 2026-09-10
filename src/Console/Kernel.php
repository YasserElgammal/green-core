<?php

namespace YasserElgammal\Green\Console;

use YasserElgammal\Green\Console\Commands\{
    ServeCommand,
    CreateAuthorizerCommand,
    CreateControllerCommand,
    CreateListenerCommand,
    CreateMigrationCommand,
    CreateModelCommand,
    CreatePolicyCommand,
    CreateProviderCommand,
    MigrateCommand,
    MigrateRollbackCommand,
    MigrateStatusCommand,
    RouteCacheCommand,
    RouteClearCommand,
    PublishConfigCommand,
    TranslationClearCommand,
    ConfigCacheCommand,
    ConfigClearCommand,
    ConfigShowCommand,
    ViewClearCommand,
};

class Kernel
{
    protected array $commands = [];

    protected array $coreCommands = [
        ServeCommand::class,
        CreateAuthorizerCommand::class,
        CreateControllerCommand::class,
        CreateListenerCommand::class,
        CreateModelCommand::class,
        CreatePolicyCommand::class,
        CreateProviderCommand::class,
        CreateMigrationCommand::class,
        MigrateCommand::class,
        MigrateRollbackCommand::class,
        MigrateStatusCommand::class,
        RouteCacheCommand::class,
        RouteClearCommand::class,
        PublishConfigCommand::class,
        TranslationClearCommand::class,
        ConfigCacheCommand::class,
        ConfigClearCommand::class,
        ConfigShowCommand::class,
        ViewClearCommand::class,
    ];

    protected \YasserElgammal\Green\Application $app;

    public function handle(): void
    {
        // Boot the main application (loads config, registers providers)
        $this->app = new \YasserElgammal\Green\Application();

        $consoleApp = new Application(
            $this->app->make(\YasserElgammal\Green\Signal\SignalDispatcher::class),
        );

        foreach ($this->coreCommands as $command) {
            $consoleApp->addCommand($this->app->make($command));
        }

        foreach ($this->commands as $command) {
            $consoleApp->addCommand($this->app->make($command));
        }

        $consoleApp->run();
    }
}
