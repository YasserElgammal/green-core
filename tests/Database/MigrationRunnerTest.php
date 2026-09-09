<?php

namespace YasserElgammal\Green\Tests\Database;

use PDO;
use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Database\Migrations\MigrationRepository;
use YasserElgammal\Green\Database\Migrations\MigrationRunner;

final class MigrationRunnerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/green_migrations_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*.php') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function test_runner_executes_pending_migration_and_rolls_it_back(): void
    {
        $file = '2026_09_08_000001_create_test_widgets_table';
        file_put_contents($this->directory . '/' . $file . '.php', <<<'PHP'
<?php
use YasserElgammal\Green\Database\Migrations\Migration;

final class CreateTestWidgetsTable extends Migration
{
    public static int $upCalls = 0;
    public static int $downCalls = 0;
    public function up(): void { self::$upCalls++; }
    public function down(): void { self::$downCalls++; }
}
PHP);

        $pdo = new PDO('sqlite::memory:');
        $repository = new InMemoryMigrationRepository($pdo);
        $runner = new MigrationRunner($pdo, $this->directory, $repository);

        self::assertSame([$file], $runner->run());
        self::assertSame(1, \CreateTestWidgetsTable::$upCalls);
        self::assertSame('ran', $runner->status()[$file]);
        self::assertSame([$file], $runner->rollback());
        self::assertSame(1, \CreateTestWidgetsTable::$downCalls);
        self::assertSame('pending', $runner->status()[$file]);
    }

    public function test_runner_rejects_a_missing_migrations_directory(): void
    {
        $missing = $this->directory . '/missing';
        $pdo = new PDO('sqlite::memory:');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Migrations directory not found');

        (new MigrationRunner($pdo, $missing, new InMemoryMigrationRepository($pdo)))->status();
    }
}

final class InMemoryMigrationRepository extends MigrationRepository
{
    /** @var array<string, int> */
    private array $ran = [];

    public function ensureTableExists(): void {}
    public function hasRun(string $migration): bool { return isset($this->ran[$migration]); }
    public function log(string $migration, int $batch): void { $this->ran[$migration] = $batch; }
    public function delete(string $migration): void { unset($this->ran[$migration]); }
    public function getLastBatch(): int { return $this->ran === [] ? 0 : max($this->ran); }
    public function getMigrationsByBatch(int $batch): array
    {
        return array_reverse(array_keys(array_filter($this->ran, fn (int $value): bool => $value === $batch)));
    }
    public function getAllRan(): array { return array_keys($this->ran); }
}
