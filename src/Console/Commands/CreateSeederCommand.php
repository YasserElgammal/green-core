<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand(
    name: 'create:seeder',
    description: 'Generate a new database seeder file from stub',
)]
class CreateSeederCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument(
            'name',
            InputArgument::REQUIRED,
            'The seeder class name in PascalCase (e.g. UserSeeder)',
        );
    }

    protected function handle(): int
    {
        $rawName   = trim((string) $this->argument('name'));
        $className = $this->toPascalCase($rawName);

        if ($className === '') {
            $this->error('Seeder name cannot be empty.');
            return self::FAILURE;
        }

        $seedersDir = $this->basePath('database/seeders');
        $prefix     = $this->nextPrefix($seedersDir);
        $fileName   = "{$prefix}_{$className}";
        $filePath   = "{$seedersDir}/{$fileName}.php";

        if ($this->fileExists($filePath)) {
            $this->error("Seeder already exists: {$filePath}");
            return self::FAILURE;
        }

        try {
            $content = $this->renderStub('seeder', [
                'class' => $className,
            ]);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->putFile($filePath, $content);
        $this->success("Seeder created: {$filePath}");

        return self::SUCCESS;
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    /**
     * Normalise any casing to PascalCase.
     * 'user_seeder' → 'UserSeeder', 'UserSeeder' → 'UserSeeder'.
     */
    private function toPascalCase(string $name): string
    {
        return str_replace('_', '', ucwords($name, '_'));
    }

    /**
     * Determine the next three-digit numeric prefix for ordering.
     *
     * Scans existing files in the directory, finds the highest prefix, and
     * returns the next one (e.g. if 002_ exists already, returns '003').
     */
    private function nextPrefix(string $directory): string
    {
        if (!is_dir($directory)) {
            return '001';
        }

        $files = glob($directory . '/*.php') ?: [];
        $max   = 0;

        foreach ($files as $file) {
            $base = basename($file);
            if (preg_match('/^(\d+)_/', $base, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }
}
