<?php

namespace YasserElgammal\Green\Tests\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use YasserElgammal\Green\Console\Commands\PublishConfigCommand;

class PublishConfigCommandTest extends TestCase
{
    private string $projectRoot;
    private string $previousCwd;

    protected function setUp(): void
    {
        $this->previousCwd = getcwd() ?: '.';
        $this->projectRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'green-publish-config-' . uniqid();
        mkdir($this->projectRoot, 0777, true);
        chdir($this->projectRoot);
    }

    protected function tearDown(): void
    {
        chdir($this->previousCwd);
        $this->removeDirectory($this->projectRoot);
    }

    public function test_it_publishes_leaf_config(): void
    {
        $tester = new CommandTester(new PublishConfigCommand());

        $exitCode = $tester->execute(['name' => 'leaf']);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'leaf.php');
        $this->assertStringContainsString("'max_depth' => 6", file_get_contents($this->projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'leaf.php'));
    }

    public function test_it_publishes_csrf_config(): void
    {
        $tester = new CommandTester(new PublishConfigCommand());

        $exitCode = $tester->execute(['name' => 'csrf']);
        $contents = file_get_contents($this->projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'csrf.php');

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'csrf.php');
        $this->assertStringContainsString('CSRF Protection Configuration', $contents);
        $this->assertStringContainsString("'ttl' => 1800", $contents);
        $this->assertStringContainsString("'/webhooks/*'", $contents);
    }

    public function test_it_publishes_connect_config(): void
    {
        $tester = new CommandTester(new PublishConfigCommand());

        $exitCode = $tester->execute(['name' => 'connect']);
        $contents = file_get_contents($this->projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'connect.php');

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'connect.php');
        $this->assertStringContainsString('Connect Configuration', $contents);
        $this->assertStringContainsString("'default' => \$_ENV['CONNECT_DEFAULT'] ?? 'default'", $contents);
        $this->assertStringContainsString("'driver' => 'symfony'", $contents);
        $this->assertStringContainsString("'connect_timeout' => (float) (\$_ENV['CONNECT_CONNECT_TIMEOUT'] ?? 5)", $contents);
    }

    public function test_it_publishes_drive_config(): void
    {
        $tester = new CommandTester(new PublishConfigCommand());

        $exitCode = $tester->execute(['name' => 'drive']);
        $contents = file_get_contents($this->projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'drive.php');

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'drive.php');
        $this->assertStringContainsString("'default' => 'local'", $contents);
        $this->assertStringContainsString("'driver' => 'local'", $contents);
        $this->assertStringContainsString("'root'   => __DIR__ . '/../public'", $contents);
    }


    public function test_it_publishes_rate_limit_config(): void
    {
        $tester = new CommandTester(new PublishConfigCommand());

        $exitCode = $tester->execute(['name' => 'rate_limit']);
        $contents = file_get_contents($this->projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'rate_limit.php');

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'rate_limit.php');
        $this->assertStringContainsString('Rate Limiting Configuration', $contents);
        $this->assertStringContainsString("'driver' => \$_ENV['RATE_LIMIT_DRIVER']", $contents);
        $this->assertStringContainsString("'max_attempts' => 60", $contents);
    }
    public function test_it_does_not_overwrite_existing_config_without_force(): void
    {
        $configPath = $this->projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'leaf.php';
        mkdir(dirname($configPath), 0777, true);
        file_put_contents($configPath, "<?php\nreturn ['max_depth' => 99];\n");

        $tester = new CommandTester(new PublishConfigCommand());
        $exitCode = $tester->execute(['name' => 'leaf']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString("'max_depth' => 99", file_get_contents($configPath));
        $this->assertStringContainsString('already exists', $tester->getDisplay());
    }

    public function test_it_overwrites_existing_config_with_force(): void
    {
        $configPath = $this->projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'leaf.php';
        mkdir(dirname($configPath), 0777, true);
        file_put_contents($configPath, "<?php\nreturn ['max_depth' => 99];\n");

        $tester = new CommandTester(new PublishConfigCommand());
        $exitCode = $tester->execute(['name' => 'leaf', '--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString("'max_depth' => 6", file_get_contents($configPath));
    }

    public function test_it_rejects_unknown_configs(): void
    {
        $tester = new CommandTester(new PublishConfigCommand());

        $exitCode = $tester->execute(['name' => 'missing']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Available configs: connect, csrf, drive, leaf, rate_limit', $tester->getDisplay());
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($path);
    }
}
