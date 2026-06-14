<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Input\InputArgument;

class CreateProviderCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('create:provider');

        $this->setDescription('Create a new service provider class')
            ->addArgument('name', InputArgument::REQUIRED, 'The provider name (e.g. AppServiceProvider)');
    }

    protected function handle(): int
    {
        $name = $this->normalizeName((string) $this->argument('name'));
        $path = $this->basePath("app/Providers/{$name}.php");

        if ($this->fileExists($path)) {
            $this->error("Provider [{$name}] already exists!");
            return self::FAILURE;
        }

        $content = $this->renderStub('provider', [
            'namespace' => 'App\\Providers',
            'class' => $name,
        ]);

        $this->putFile($path, $content);
        $this->success("Provider [{$name}] created at app/Providers/{$name}.php");

        return self::SUCCESS;
    }

    private function normalizeName(string $name): string
    {
        $name = trim(str_replace('\\', '/', $name), '/');
        $name = basename($name);

        if (!str_ends_with($name, 'ServiceProvider')) {
            $name .= 'ServiceProvider';
        }

        return ucfirst($name);
    }
}
