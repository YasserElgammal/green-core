<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Input\InputArgument;

class CreateEventCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('create:event');

        $this->setDescription('Create a new event class')
            ->addArgument('name', InputArgument::REQUIRED, 'The event name (e.g. UserCreated)');
    }

    protected function handle(): int
    {
        $name = $this->normalizeName((string) $this->argument('name'));
        $path = $this->basePath("app/Events/{$name}.php");

        if ($this->fileExists($path)) {
            $this->error("Event [{$name}] already exists!");
            return self::FAILURE;
        }

        $content = $this->renderStub('event', [
            'namespace' => 'App\\Events',
            'class' => $name,
        ]);

        $this->putFile($path, $content);
        $this->success("Event [{$name}] created at app/Events/{$name}.php");

        return self::SUCCESS;
    }

    private function normalizeName(string $name): string
    {
        $name = trim(str_replace('\\', '/', $name), '/');

        return ucfirst(basename($name));
    }
}
