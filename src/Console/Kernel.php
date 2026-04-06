<?php

namespace YasserElgammal\Green\Console;

use YasserElgammal\Green\Console\Commands\ServeCommand;
use YasserElgammal\Green\Console\Commands\CreateControllerCommand;
use YasserElgammal\Green\Console\Commands\CreateModelCommand;

class Kernel
{
    protected array $commands = [];

    protected array $coreCommands = [
        ServeCommand::class,
        CreateControllerCommand::class,
        CreateModelCommand::class,
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
