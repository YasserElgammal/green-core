<?php

namespace YasserElgammal\Green\Tests\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use YasserElgammal\Green\Config\ConfigCache;
use YasserElgammal\Green\Config\Repository;
use YasserElgammal\Green\Config\ConfigManager;
use YasserElgammal\Green\Config\CoreDefinitions;
use YasserElgammal\Green\Config\Security\SecretRedactor;
use YasserElgammal\Green\Console\Commands\ConfigCacheCommand;
use YasserElgammal\Green\Console\Commands\ConfigClearCommand;
use YasserElgammal\Green\Console\Commands\ConfigShowCommand;

final class ConfigCommandsTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/green-command-config-' . bin2hex(random_bytes(6)) . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) unlink($this->path);
    }

    public function test_cache_and_clear_commands(): void
    {
        $cache = new ConfigCache($this->path);
        $manager = new ConfigManager(
            dirname($this->path),
            dirname($this->path) . '/missing-config',
            CoreDefinitions::registry(),
            $cache,
        );
        $repository = $manager->load();
        $manager->lock();

        self::assertSame(0, (new CommandTester(new ConfigCacheCommand($repository, $cache, $manager)))->execute([]));
        self::assertFileExists($this->path);
        self::assertSame(0, (new CommandTester(new ConfigClearCommand($cache)))->execute([]));
        self::assertFileDoesNotExist($this->path);
    }

    public function test_show_redacts_secrets(): void
    {
        $definitions = CoreDefinitions::registry();
        $repository = new Repository($definitions->defaults(dirname($this->path)) + ['api_token' => 'top-secret']);
        $show = new CommandTester(new ConfigShowCommand($repository, new SecretRedactor()));

        self::assertSame(0, $show->execute([]));
        self::assertStringContainsString('********', $show->getDisplay());
        self::assertStringNotContainsString('top-secret', $show->getDisplay());
    }
}
