<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Input\InputArgument;

class CreateModelCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('create:model');

        $this
            ->setDescription('Create a new model class')
            ->addArgument('name', InputArgument::REQUIRED, 'The model name (e.g. User)');
    }

    protected function handle(): int
    {
        $name  = ucfirst($this->argument('name'));
        $table = $this->guessTableName($name);
        $path  = $this->basePath("app/Models/{$name}.php");

        if ($this->fileExists($path)) {
            $this->error("Model [{$name}] already exists!");
            return self::FAILURE;
        }

        $content = $this->renderStub('model', [
            'namespace'  => 'App\\Models',
            'class'      => $name,
            'table'      => $table,
            'primaryKey' => 'id',
        ]);

        $this->putFile($path, $content);
        $this->success("Model [{$name}] created at app/Models/{$name}.php");

        return self::SUCCESS;
    }

    /**
     * User       → users
     * BlogPost   → blog_posts
     * OrderItem  → order_items
     */
    private function guessTableName(string $name): string
    {
        $snake = strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
        return $snake . 's';
    }
}
