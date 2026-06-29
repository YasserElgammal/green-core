<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Input\InputArgument;

class CreateListenerCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('create:listener');

        $this->setDescription('Create a new listener class')
            ->addArgument('name', InputArgument::REQUIRED, 'The listener name (e.g. SendWelcomeEmail)');
    }

    protected function handle(): int
    {
        $name = $this->normalizeName((string) $this->argument('name'));
        $path = $this->basePath("app/Listeners/{$name}.php");

        if ($this->fileExists($path)) {
            $this->error("Listener [{$name}] already exists!");
            return self::FAILURE;
        }

        $content = $this->renderStub('listener', [
            'namespace' => 'App\\Listeners',
            'class' => $name,
        ]);

        $this->putFile($path, $content);
        $this->success("Listener [{$name}] created at app/Listeners/{$name}.php");

        return self::SUCCESS;
    }

    private function normalizeName(string $name): string
    {
        $name = trim(str_replace('\\', '/', $name), '/');

        return ucfirst(basename($name));
    }
}
