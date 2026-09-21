<?php

namespace YasserElgammal\Green\Tests\Config;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Config\ConfigCache;
use YasserElgammal\Green\Config\ConfigManager;
use YasserElgammal\Green\Config\ConfigState;
use YasserElgammal\Green\Config\CoreDefinitions;
use YasserElgammal\Green\Config\Exceptions\ConfigurationException;

final class ConfigManagerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/green-manager-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->directory);
    }

    public function test_lifecycle_becomes_ready_after_loading(): void
    {
        $manager = $this->manager();
        self::assertSame(ConfigState::Unloaded, $manager->state());

        $repository = $manager->load();

        self::assertSame(ConfigState::Ready, $manager->state());
        self::assertSame($repository, $manager->repository());
    }

    public function test_configuration_cannot_be_loaded_twice(): void
    {
        $manager = $this->manager();
        $manager->load();

        $this->expectException(ConfigurationException::class);
        $manager->load();
    }

    public function test_repository_is_unavailable_before_loading(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->manager()->repository();
    }

    private function manager(): ConfigManager
    {
        return new ConfigManager(
            $this->directory,
            $this->directory . '/config',
            CoreDefinitions::registry(),
            new ConfigCache($this->directory . '/bootstrap/cache/config.php'),
        );
    }
}
