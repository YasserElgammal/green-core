<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Input\InputArgument;

class CreatePolicyCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('create:policy');

        $this->setDescription('Create a new policy class')
            ->addArgument('name', InputArgument::REQUIRED, 'The policy name (e.g. PostPolicy)');
    }

    protected function handle(): int
    {
        $name = $this->normalizeName((string) $this->argument('name'), 'Policy');
        $path = $this->basePath("app/Policies/{$name}.php");

        if ($this->fileExists($path)) {
            $this->error("Policy [{$name}] already exists!");
            return self::FAILURE;
        }

        $content = $this->renderStub('policy', [
            'namespace' => 'App\\Policies',
            'class' => $name,
        ]);

        $this->putFile($path, $content);
        $this->success("Policy [{$name}] created at app/Policies/{$name}.php");

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
