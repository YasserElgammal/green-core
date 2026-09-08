<?php

namespace YasserElgammal\Green\Tests\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use YasserElgammal\Green\Config\Typed\ViewConfig;
use YasserElgammal\Green\Console\Commands\ViewClearCommand;

final class ViewClearCommandTest extends TestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/green-view-cache-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cachePath)) {
            $nestedPath = $this->cachePath . '/nested';
            if (is_dir($nestedPath)) {
                rmdir($nestedPath);
            }
            rmdir($this->cachePath);
        }
    }

    public function test_it_removes_compiled_views_and_preserves_cache_directory(): void
    {
        mkdir($this->cachePath . '/nested', recursive: true);
        file_put_contents($this->cachePath . '/template.php', 'compiled');
        file_put_contents($this->cachePath . '/nested/template.php', 'compiled');

        $tester = new CommandTester(new ViewClearCommand($this->config()));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertDirectoryExists($this->cachePath);
        self::assertSame([], array_values(array_diff(scandir($this->cachePath), ['.', '..'])));
        self::assertStringContainsString('View cache cleared.', $tester->getDisplay());
    }

    public function test_it_succeeds_when_cache_directory_does_not_exist(): void
    {
        $tester = new CommandTester(new ViewClearCommand($this->config()));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('View cache cleared.', $tester->getDisplay());
    }

    public function test_it_rejects_an_unsafe_cache_path(): void
    {
        $config = new ViewConfig(
            path: dirname($this->cachePath) . '/views',
            cache: true,
            cachePath: DIRECTORY_SEPARATOR,
            debug: false,
        );
        $tester = new CommandTester(new ViewClearCommand($config));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('not safe to clear', $tester->getDisplay());
    }

    private function config(): ViewConfig
    {
        return new ViewConfig(
            path: dirname($this->cachePath) . '/views',
            cache: true,
            cachePath: $this->cachePath,
            debug: false,
        );
    }
}
