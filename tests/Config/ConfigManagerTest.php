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

    public function test_lifecycle_must_load_then_lock(): void
    {
        $manager = $this->manager();
        self::assertSame(ConfigState::Collecting, $manager->state());

        $repository = $manager->load();
        self::assertSame(ConfigState::Loaded, $manager->state());
        $manager->lock();

        self::assertSame(ConfigState::Locked, $manager->state());
        self::assertTrue($repository->isLocked());
    }

    public function test_invalid_lifecycle_transition_is_rejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->manager()->lock();
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
