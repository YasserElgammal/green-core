<?php

namespace YasserElgammal\Green\Tests\Config;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Config\CoreDefinitions;
use YasserElgammal\Green\Config\Repository;
use YasserElgammal\Green\Config\Sources\EnvironmentSource;
use YasserElgammal\Green\Config\Typed\ViewConfig;

final class ViewConfigEnvironmentTest extends TestCase
{
    /** @var array<string, array{exists: bool, value: mixed}> */
    private array $environment = [];

    protected function setUp(): void
    {
        foreach (['VIEW_CACHE', 'VIEW_CACHE_PATH'] as $key) {
            $this->environment[$key] = [
                'exists' => array_key_exists($key, $_ENV),
                'value' => $_ENV[$key] ?? null,
            ];
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->environment as $key => $state) {
            if ($state['exists']) {
                $_ENV[$key] = $state['value'];
            } else {
                unset($_ENV[$key]);
            }
        }
    }

    public function test_view_cache_settings_are_loaded_from_environment(): void
    {
        $_ENV['VIEW_CACHE'] = 'false';
        $_ENV['VIEW_CACHE_PATH'] = '/runtime/view-cache';

        $source = new EnvironmentSource(CoreDefinitions::registry()->environmentMap());
        $config = ViewConfig::fromRepository(new Repository($source->load()));

        self::assertFalse($config->cache);
        self::assertSame('/runtime/view-cache', $config->cachePath);

        $_ENV['VIEW_CACHE'] = 'true';
        $config = ViewConfig::fromRepository(new Repository($source->load()));

        self::assertTrue($config->cache);
    }
}
