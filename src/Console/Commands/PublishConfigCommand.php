<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'publish:config',
    description: 'Publish a Green Framework configuration file',
)]
class PublishConfigCommand extends BaseCommand
{
    /** @var array<string,string> */
    private array $configs = [
        'connect' => 'config.connect',
        'csrf' => 'config.csrf',
        'drive' => 'config.drive',
        'leaf' => 'config.leaf',
    ];

    protected function configure(): void
    {
        $this
            ->setName('publish:config')
            ->setDescription('Publish a Green Framework configuration file')
            ->addArgument('name', InputArgument::REQUIRED, 'The config name to publish, e.g. leaf, csrf, connect, or drive')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite the config file if it already exists');
    }

    protected function handle(): int
    {
        $name = strtolower((string) $this->argument('name'));

        if (!isset($this->configs[$name])) {
            $available = implode(', ', array_keys($this->configs));
            $this->error("Config [{$name}] is not publishable. Available configs: {$available}");

            return Command::FAILURE;
        }

        $path = $this->basePath("config/{$name}.php");

        if ($this->fileExists($path) && !$this->option('force')) {
            $this->warn("Config [config/{$name}.php] already exists. Use --force to overwrite it.");

            return Command::FAILURE;
        }

        $content = $this->renderStub($this->configs[$name]);
        $this->putFile($path, $content);

        $this->success("Config [config/{$name}.php] published successfully.");

        return Command::SUCCESS;
    }
}
