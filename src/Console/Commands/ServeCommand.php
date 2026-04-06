<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'serve',
    description: 'Start a development server',
)]
class ServeCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'The host address to serve the application on', 'localhost')
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'The port to serve the application on', 8000);
    }

    protected function handle(): int
    {
        $host = $this->option('host') ?? 'localhost';
        $port = $this->option('port') ?? 8000;

        $this->info("Starting development server on http://{$host}:{$port}");

        // Start the PHP built-in server
        $publicDir = './public';
        $command = "php -S {$host}:{$port} -t {$publicDir}";

        $this->line("Running: {$command}");
        passthru($command);

        return Command::SUCCESS;
    }
}
