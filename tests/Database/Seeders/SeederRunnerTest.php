<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Database\Seeders;

use PDO;
use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Database\Seeders\Seeder;
use YasserElgammal\Green\Database\Seeders\SeederRunner;

/**
 * Tests for SeederRunner using an in-memory SQLite database.
 * No live database required.
 *
 * Each test uses a unique class name suffix (based on test index) so that
 * require_once across tests within the same process never causes a class
 * redeclaration fatal error.
 */
class SeederRunnerTest extends TestCase
{
    private PDO $pdo;
    private string $seedersDir;

    /** @var string[] Files written by the current test — removed in tearDown */
    private array $writtenFiles = [];

    /** Auto-incrementing suffix to keep class names unique across tests */
    private static int $seq = 0;

    protected function setUp(): void
    {
        self::$seq++;

        // In-memory SQLite database
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Unique temp directory per test
        $this->seedersDir    = sys_get_temp_dir() . '/green_seeders_' . self::$seq . '_' . uniqid('', true);
        $this->writtenFiles  = [];
        mkdir($this->seedersDir, 0755, true);

        // Simple test table
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
    }

    protected function tearDown(): void
    {
        foreach ($this->writtenFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->seedersDir)) {
            rmdir($this->seedersDir);
        }
    }

    // ─── Discovery ─────────────────────────────────────────────────────────

    public function test_run_returns_empty_array_when_no_seeders_exist(): void
    {
        $runner = new SeederRunner($this->pdo, $this->seedersDir);
        $result = $runner->run();

        $this->assertSame([], $result);
    }

    public function test_run_throws_when_directory_does_not_exist(): void
    {
        $runner = new SeederRunner($this->pdo, '/nonexistent/path/seeders');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Seeders directory not found/');

        $runner->run();
    }

    public function test_run_throws_when_class_does_not_exist_in_file(): void
    {
        $this->writeRaw('001_BrokenSeeder' . self::$seq . '.php', '<?php class WrongClass' . self::$seq . ' {}');

        $runner = new SeederRunner($this->pdo, $this->seedersDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found in file/');

        $runner->run();
    }

    public function test_run_throws_when_class_does_not_extend_seeder(): void
    {
        $cls = 'NotASeeder' . self::$seq;
        $this->writeRaw('001_' . $cls . '.php', "<?php class {$cls} {}");

        $runner = new SeederRunner($this->pdo, $this->seedersDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/must extend/');

        $runner->run();
    }

    // ─── Execution ─────────────────────────────────────────────────────────

    public function test_run_executes_seeder_and_returns_class_name(): void
    {
        $cls = 'SimpleUserSeeder' . self::$seq;
        $seq = self::$seq; // capture for heredoc

        $this->writeSeeder("001_{$cls}.php", $cls, <<<PHP
            public function run(): void
            {
                \$GLOBALS['testPdo{$seq}']->exec("INSERT INTO users (name) VALUES ('Alice')");
            }
            PHP
        );

        $GLOBALS["testPdo{$seq}"] = $this->pdo;

        $runner = new SeederRunner($this->pdo, $this->seedersDir);
        $ran    = $runner->run();

        $this->assertSame([$cls], $ran);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertSame(1, $count);

        unset($GLOBALS["testPdo{$seq}"]);
    }

    public function test_run_single_seeder_by_class_name(): void
    {
        $alpha = 'AlphaSeeder' . self::$seq;
        $beta  = 'BetaSeeder' . self::$seq;

        $this->writeSeeder("001_{$alpha}.php", $alpha, 'public function run(): void {}');
        $this->writeSeeder("002_{$beta}.php",  $beta,  'public function run(): void {}');

        $runner = new SeederRunner($this->pdo, $this->seedersDir);
        $ran    = $runner->run($beta);

        $this->assertSame([$beta], $ran);
    }

    public function test_run_throws_for_unknown_class_name(): void
    {
        $alpha = 'KnownSeeder' . self::$seq;
        $this->writeSeeder("001_{$alpha}.php", $alpha, 'public function run(): void {}');

        $runner = new SeederRunner($this->pdo, $this->seedersDir);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/was not found/');

        $runner->run('DoesNotExist');
    }

    public function test_run_rolls_back_transaction_on_failure(): void
    {
        $cls = 'FailingSeeder' . self::$seq;
        $seq = self::$seq;

        $this->writeSeeder("001_{$cls}.php", $cls, <<<PHP
            public function run(): void
            {
                \$GLOBALS['testPdo{$seq}']->exec("INSERT INTO users (name) VALUES ('Bob')");
                throw new \RuntimeException('Seeder failure');
            }
            PHP
        );

        $GLOBALS["testPdo{$seq}"] = $this->pdo;

        $runner = new SeederRunner($this->pdo, $this->seedersDir);

        try {
            $runner->run();
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($cls, $e->getMessage());
        }

        // The insert should have been rolled back
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertSame(0, $count);

        unset($GLOBALS["testPdo{$seq}"]);
    }

    // ─── Truncation ────────────────────────────────────────────────────────

    public function test_seeder_truncates_declared_tables_before_run(): void
    {
        // Pre-populate the table
        $this->pdo->exec("INSERT INTO users (name) VALUES ('Existing')");

        $cls = 'TruncatingSeeder' . self::$seq;
        $seq = self::$seq;

        $this->writeSeederWithTruncate("001_{$cls}.php", $cls, ['users'], <<<PHP
            public function run(): void
            {
                \$GLOBALS['testPdo{$seq}']->exec("INSERT INTO users (name) VALUES ('Fresh')");
            }
            PHP
        );

        $GLOBALS["testPdo{$seq}"] = $this->pdo;

        $runner = new SeederRunner($this->pdo, $this->seedersDir);
        $runner->run();

        $names = $this->pdo->query('SELECT name FROM users')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['Fresh'], $names);

        unset($GLOBALS["testPdo{$seq}"]);
    }

    public function test_truncate_rejects_invalid_table_name(): void
    {
        $cls = 'BadTruncateSeeder' . self::$seq;
        $this->writeSeederWithTruncate(
            "001_{$cls}.php",
            $cls,
            ['users; DROP TABLE users--'],
            'public function run(): void {}'
        );

        $runner = new SeederRunner($this->pdo, $this->seedersDir);

        // The InvalidArgumentException is wrapped by runSeeder() into a RuntimeException
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid table name/');

        $runner->run();
    }

    // ─── File-name to class resolution ─────────────────────────────────────

    public function test_numeric_prefix_is_stripped_from_class_name(): void
    {
        $cls = 'AliceSeeder' . self::$seq;
        $this->writeSeeder("001_{$cls}.php", $cls, 'public function run(): void {}');

        $runner = new SeederRunner($this->pdo, $this->seedersDir);
        $ran    = $runner->run();

        $this->assertSame([$cls], $ran);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    private function writeRaw(string $file, string $content): void
    {
        $path = $this->seedersDir . '/' . $file;
        file_put_contents($path, $content);
        $this->writtenFiles[] = $path;
    }

    private function writeSeeder(string $file, string $class, string $runBody): void
    {
        $this->writeRaw($file, <<<PHP
            <?php
            use YasserElgammal\Green\Database\Seeders\Seeder;
            class {$class} extends Seeder
            {
                {$runBody}
            }
            PHP
        );
    }

    private function writeSeederWithTruncate(string $file, string $class, array $truncate, string $runBody): void
    {
        $truncateExport = var_export($truncate, true);
        $this->writeRaw($file, <<<PHP
            <?php
            use YasserElgammal\Green\Database\Seeders\Seeder;
            class {$class} extends Seeder
            {
                protected array \$truncate = {$truncateExport};
                {$runBody}
            }
            PHP
        );
    }
}
