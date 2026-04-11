<?php

namespace YasserElgammal\Green\Database\Migrations;

use YasserElgammal\Green\Database\Schema\Schema;
use PDO;

/**
 * Loads migration files, runs pending ones, and handles rollbacks.
 * Works alongside MigrationRepository for tracking.
 */
class MigrationRunner
{
    private MigrationRepository $repository;
    private bool $dryRun = false;

    /** @var string[] SQL statements collected in dry-run mode */
    private array $dryRunOutput = [];

    public function __construct(
        private readonly PDO    $pdo,
        private readonly string $migrationsPath,
        ?MigrationRepository    $repository = null,
    ) {
        $this->repository = $repository ?? new MigrationRepository($pdo);
    }

    // ─── Configuration ─────────────────────────────────────────────────────

    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
        Schema::setDryRun($dryRun);
    }

    public function getDryRunOutput(): array
    {
        return $this->dryRunOutput;
    }

    // ─── Run ───────────────────────────────────────────────────────────────

    /**
     * Run all pending (not yet executed) migrations.
     *
     * @return string[] Names of migrations that were run.
     */
    public function run(): array
    {
        $this->repository->ensureTableExists();

        $pending = $this->getPendingMigrations();

        if (empty($pending)) {
            return [];
        }

        $batch = $this->repository->getLastBatch() + 1;
        $ran   = [];

        foreach ($pending as $file => $class) {
            $this->runMigration($file, $class, $batch);
            $ran[] = $file;
        }

        if ($this->dryRun) {
            $this->dryRunOutput = Schema::getDryRunLog();
            Schema::clearDryRunLog();
        }

        return $ran;
    }

    // ─── Rollback ──────────────────────────────────────────────────────────

    /**
     * Roll back the last batch of migrations.
     *
     * @return string[] Names of migrations that were rolled back.
     */
    public function rollback(): array
    {
        $this->repository->ensureTableExists();

        $lastBatch = $this->repository->getLastBatch();

        if ($lastBatch === 0) {
            return [];
        }

        $migrationNames = $this->repository->getMigrationsByBatch($lastBatch);
        $rolled         = [];

        foreach ($migrationNames as $name) {
            $this->rollbackMigration($name);
            $rolled[] = $name;
        }

        if ($this->dryRun) {
            $this->dryRunOutput = Schema::getDryRunLog();
            Schema::clearDryRunLog();
        }

        return $rolled;
    }

    /**
     * Roll back ALL migrations (use with caution).
     *
     * @return string[]
     */
    public function reset(): array
    {
        $this->repository->ensureTableExists();

        $all   = array_reverse($this->repository->getAllRan());
        $rolled = [];

        foreach ($all as $name) {
            $this->rollbackMigration($name);
            $rolled[] = $name;
        }

        return $rolled;
    }

    // ─── Status ────────────────────────────────────────────────────────────

    /**
     * Return migration status: 'ran' or 'pending'.
     *
     * @return array<string, string>
     */
    public function status(): array
    {
        $this->repository->ensureTableExists();

        $all     = $this->loadMigrationFiles();
        $ran     = $this->repository->getAllRan();
        $status  = [];

        foreach (array_keys($all) as $file) {
            $status[$file] = in_array($file, $ran, true) ? 'ran' : 'pending';
        }

        return $status;
    }

    // ─── Internal ──────────────────────────────────────────────────────────

    /**
     * Return pending migrations: files that have not been run yet.
     * Order is determined by file name (timestamp prefix ensures ordering).
     *
     * @return array<string, class-string>  file => class name
     */
    private function getPendingMigrations(): array
    {
        $all    = $this->loadMigrationFiles();
        $ran    = $this->repository->getAllRan();
        $pending = [];

        foreach ($all as $file => $class) {
            if (!in_array($file, $ran, true)) {
                $pending[$file] = $class;
            }
        }

        return $pending;
    }

    /**
     * Discover all migration files in the migrations directory.
     * Files must be named like: 2024_01_01_000001_create_users_table.php
     *
     * @return array<string, class-string>
     */
    private function loadMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            throw new \RuntimeException("Migrations directory not found: {$this->migrationsPath}");
        }

        $files = glob($this->migrationsPath . '/*.php');
        if ($files === false) {
            return [];
        }

        sort($files);  // timestamp prefix ensures chronological order

        $migrations = [];

        foreach ($files as $filePath) {
            $fileName = basename($filePath, '.php');
            $class    = $this->fileNameToClass($fileName);

            require_once $filePath;

            if (!class_exists($class)) {
                throw new \RuntimeException(
                    "Migration class `{$class}` not found in file `{$filePath}`."
                );
            }

            $migrations[$fileName] = $class;
        }

        return $migrations;
    }

    private function runMigration(string $file, string $class, int $batch): void
    {
        /** @var Migration $instance */
        $instance = new $class();
        $instance->up();

        if (!$this->dryRun) {
            $this->repository->log($file, $batch);
        }
    }

    private function rollbackMigration(string $name): void
    {
        $filePath = $this->migrationsPath . '/' . $name . '.php';

        if (!file_exists($filePath)) {
            throw new \RuntimeException("Migration file not found for rollback: {$filePath}");
        }

        $class = $this->fileNameToClass($name);
        require_once $filePath;

        /** @var Migration $instance */
        $instance = new $class();
        $instance->down();

        if (!$this->dryRun) {
            $this->repository->delete($name);
        }
    }

    /**
     * Convert a snake_case file name to a PascalCase class name.
     * e.g. 2024_01_01_000001_create_users_table → CreateUsersTable
     */
    private function fileNameToClass(string $fileName): string
    {
        // Strip leading timestamp prefix (YYYY_MM_DD_HHMMSS_)
        $name = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $fileName);
        return str_replace('_', '', ucwords($name, '_'));
    }
}
