<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand(
    name: 'create:migration',
    description: 'Generate a new migration file from stub',
)]
class CreateMigrationCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument(
            'name',
            InputArgument::REQUIRED,
            'The migration name in snake_case (e.g. create_users_table)',
        );
    }

    protected function handle(): int
    {
        // Normalise: lowercase, spaces → underscores
        $rawName = trim((string) $this->argument('name'));
        $name    = strtolower(preg_replace('/\s+/', '_', $rawName));

        if ($name === '') {
            $this->error('Migration name cannot be empty.');
            return self::FAILURE;
        }

        $timestamp = date('Y_m_d_His');
        $fileName  = "{$timestamp}_{$name}";
        $className = $this->toClassName($name);
        
        $intent  = $this->guessActionDetails($name);
        $action  = $intent['action'];
        $table   = $intent['table'];
        $column  = $intent['column'];
        $subType = $intent['type'] ?? 'add';

        $migrationsDir = $this->basePath('database/migrations');
        $filePath      = "{$migrationsDir}/{$fileName}.php";

        if ($this->fileExists($filePath)) {
            $this->error("Migration already exists: {$filePath}");
            return self::FAILURE;
        }

        try {
            $stubName = $action === 'alter' ? 'migration.alter' : 'migration.create';
            
            $content = $this->renderStub($stubName, [
                'class'  => $className,
                'table'  => $table,
                'column' => $column ?? 'column_name',
            ]);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->putFile($filePath, $content);

        $this->success("Migration created: {$filePath}");

        return self::SUCCESS;
    }

    /**
     * Helpers
     */

    /**
     * snake_case → PascalCase class name
     *   create_users_table → CreateUsersTable
     */
    private function toClassName(string $name): string
    {
        return str_replace('_', '', ucwords($name, '_'));
    }

    /**
     * Infer 'create' or 'alter' actions from common migration name conventions.
     * Returns ['action' => 'create'|'alter', 'table' => string, 'column' => ?string, 'type' => ?string]
     */
    private function guessActionDetails(string $name): array
    {
        if (preg_match('/^create_(.+)_table$/', $name, $m)) {
            return ['action' => 'create', 'table' => $m[1], 'column' => null];
        }

        if (preg_match('/^(add|remove|drop)_(.+)_(to|from|in)_(.+)$/', $name, $m)) {
            return ['action' => 'alter', 'table' => $m[4], 'column' => $m[2], 'type' => $m[1]];
        }

        return ['action' => 'create', 'table' => $name, 'column' => null];
    }
}
