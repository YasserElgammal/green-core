<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CreateControllerCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('create:controller');

        $this->setDescription('Create a new controller class')
            ->addArgument('name', InputArgument::REQUIRED, 'The controller name (e.g. UserController)')
            ->addOption('resource', 'r', InputOption::VALUE_NONE, 'Generate a resource controller with CRUD methods');
    }

    protected function handle(): int
    {
        $name     = $this->normalizeName($this->argument('name'));
        $path     = $this->basePath("app/Controllers/{$name}.php");

        if ($this->fileExists($path)) {
            $this->error("Controller [{$name}] already exists!");
            return self::FAILURE;
        }

        $content = $this->renderStub('controller.plain', [
            'namespace'  => 'App\\Controllers',
            'class'      => $name,
        ]);

        $this->putFile($path, $content);
        $this->success("Controller [{$name}] created at app/Controllers/{$name}.php");

        return self::SUCCESS;
    }

    private function normalizeName(string $name): string
    {
        // Ensure it ends with "Controller"
        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }
        return ucfirst($name);
    }
}
