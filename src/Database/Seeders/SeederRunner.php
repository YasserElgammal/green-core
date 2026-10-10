<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\Seeders;

use PDO;

/**
 * SeederRunner discovers and executes seeder files.
 *
 * Design mirrors MigrationRunner:
 *   - Files live in a configurable directory (default: database/seeders/)
 *   - Files are sorted alphabetically; numeric prefixes (001_, 002_) control order
 *   - Each seeder runs inside a transaction; failure rolls back and stops execution
 *   - No execution tracking — seeders are designed to be re-runnable
 *
 * Truncation: if a seeder declares $truncate tables, they are cleared
 * before run() is called (inside the same transaction).
 */
class SeederRunner
{
    public function __construct(
        private readonly PDO    $pdo,
        private readonly string $seedersPath,
    ) {}

    // ─── Run ───────────────────────────────────────────────────────────────

    /**
     * Run all seeders in the directory, or a single seeder by class name.
     *
     * @param  string|null  $class  Short class name (e.g. 'UserSeeder') to run only that one.
     * @return string[]  Class names of seeders that were run.
     */
    public function run(?string $class = null): array
    {
        $all = $this->loadSeederFiles();

        if (empty($all)) {
            return [];
        }

        if ($class !== null) {
            if (!isset($all[$class])) {
                throw new \InvalidArgumentException(
                    "Seeder class [{$class}] was not found in [{$this->seedersPath}]. " .
                    'Available seeders: [' . implode(', ', array_keys($all)) . '].'
                );
            }
            $all = [$class => $all[$class]];
        }

        $ran = [];

        foreach ($all as $className => $filePath) {
            $this->runSeeder($className);
            $ran[] = $className;
        }

        return $ran;
    }

    /**
     * Truncate all tables declared across all seeders, then run them.
     *
     * Used by db:seed --fresh.
     *
     * @return string[]  Class names of seeders that were run.
     */
    public function fresh(): array
    {
        $all = $this->loadSeederFiles();

        // Collect all truncate tables across all seeders
        $tablesToTruncate = [];
        foreach ($all as $className => $filePath) {
            $instance = new $className();
            foreach ($instance->getTruncateTables() as $table) {
                $tablesToTruncate[$table] = true;
            }
        }

        foreach (array_keys($tablesToTruncate) as $table) {
            $this->truncateTable($table);
        }

        // Now run all seeders without per-seeder truncation
        return $this->run();
    }

    // ─── Internal ──────────────────────────────────────────────────────────

    /**
     * Discover all seeder files in the seeders directory.
     *
     * Files must be PHP files (e.g. 001_UserSeeder.php).
     * They are sorted alphabetically so numeric prefixes control order.
     *
     * @return array<string, string>  class name => file path
     */
    private function loadSeederFiles(): array
    {
        if (!is_dir($this->seedersPath)) {
            throw new \RuntimeException("Seeders directory not found: {$this->seedersPath}");
        }

        $files = glob($this->seedersPath . '/*.php');
        if ($files === false || empty($files)) {
            return [];
        }

        sort($files); // alphabetical order; numeric prefix drives execution order

        $seeders = [];

        foreach ($files as $filePath) {
            $fileName  = basename($filePath, '.php');
            $className = $this->fileNameToClass($fileName);

            require_once $filePath;

            if (!class_exists($className)) {
                throw new \RuntimeException(
                    "Seeder class [{$className}] not found in file [{$filePath}]. " .
                    'The class name must match the file name (without numeric prefix).'
                );
            }

            if (!is_subclass_of($className, Seeder::class)) {
                throw new \RuntimeException(
                    "Class [{$className}] in [{$filePath}] must extend [" . Seeder::class . '].'
                );
            }

            $seeders[$className] = $filePath;
        }

        return $seeders;
    }

    /**
     * Execute a single seeder class inside a transaction.
     */
    private function runSeeder(string $className): void
    {
        /** @var Seeder $seeder */
        $seeder = new $className();

        $this->pdo->beginTransaction();

        try {
            // Truncate declared tables before seeding
            foreach ($seeder->getTruncateTables() as $table) {
                $this->truncateTable($table);
            }

            $seeder->run();

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw new \RuntimeException(
                "Seeder [{$className}] failed: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Truncate a table using a raw PDO statement.
     *
     * Uses DELETE instead of TRUNCATE so it works inside transactions
     * on all supported databases (MySQL, SQLite).
     */
    private function truncateTable(string $table): void
    {
        // Validate the table name to prevent SQL injection
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException(
                "Invalid table name [{$table}] in seeder truncate declaration."
            );
        }

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $this->pdo->exec("DELETE FROM \"{$table}\"");
            $this->pdo->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        } else {
            // MySQL / MariaDB: temporarily disable FK checks for the truncate
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $this->pdo->exec("TRUNCATE TABLE `{$table}`");
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * Convert a seeder file name to the expected class name.
     *
     * Strips a leading numeric prefix (e.g. 001_, 002_) then converts
     * the remainder from snake_case to PascalCase.
     *
     *   001_user_seeder    → UserSeeder
     *   002_PostSeeder     → PostSeeder  (already PascalCase, still works)
     */
    private function fileNameToClass(string $fileName): string
    {
        // Strip numeric prefix: 001_, 01_, 1_ etc.
        $name = preg_replace('/^\d+_/', '', $fileName);

        // If already PascalCase (e.g. "UserSeeder"), return as-is
        if (preg_match('/^[A-Z]/', $name)) {
            return $name;
        }

        // snake_case → PascalCase
        return str_replace('_', '', ucwords($name, '_'));
    }
}
