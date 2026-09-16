<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CreateObserverCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('create:observer');

        $this->setDescription('Create a new observer class')
            ->addArgument('name', InputArgument::REQUIRED, 'The observer name (e.g. UserObserver)')
            ->addOption('model', 'm', InputOption::VALUE_REQUIRED, 'The model class to observe (e.g. User)');
    }

    protected function handle(): int
    {
        $name  = $this->normalizeName((string) $this->argument('name'), 'Observer');
        $model = $this->resolveModelName($name);
        $path  = $this->basePath("app/Observers/{$name}.php");

        if ($this->fileExists($path)) {
            $this->error("Observer [{$name}] already exists!");
            return self::FAILURE;
        }

        $content = $this->renderStub('observer', [
            'namespace' => 'App\\Observers',
            'class'     => $name,
            'model'     => $model,
        ]);

        $this->putFile($path, $content);
        $this->success("Observer [{$name}] created at app/Observers/{$name}.php");

        return self::SUCCESS;
    }

    /**
     * Derive the model name from the observer name or the --model option.
     *
     * UserObserver → User
     * PostObserver → Post (with --model=Post, uses the explicit value)
     */
    private function resolveModelName(string $observerName): string
    {
        $explicit = $this->input->getOption('model');

        if ($explicit !== null) {
            return ucfirst(trim($explicit));
        }

        // Strip 'Observer' suffix to infer model name
        if (str_ends_with($observerName, 'Observer')) {
            return substr($observerName, 0, -8);
        }

        return $observerName;
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
