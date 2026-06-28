<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Input\InputArgument;

class CreateAuthorizerCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('create:authorizer');

        $this->setDescription('Create a new authorizer class')
            ->addArgument('name', InputArgument::REQUIRED, 'The authorizer name (e.g. PostAuthorizer)');
    }

    protected function handle(): int
    {
        $name = $this->normalizeName((string) $this->argument('name'), 'Authorizer');
        $path = $this->basePath("app/Authorizers/{$name}.php");

        if ($this->fileExists($path)) {
            $this->error("Authorizer [{$name}] already exists!");
            return self::FAILURE;
        }

        $content = $this->renderStub('authorizer', [
            'namespace' => 'App\\Authorizers',
            'class' => $name,
        ]);

        $this->putFile($path, $content);
        $this->success("Authorizer [{$name}] created at app/Authorizers/{$name}.php");

        return self::SUCCESS;
    }

    private function normalizeName(string $name, string $suffix): string
    {
        $name = trim(str_replace('\\', '/', $name), '/');
        $name = basename($name);

        if (!str_ends_with($name, $suffix)) {
            $name .= $suffix;
        }

        return ucfirst($name);
    }
}
