<?php

namespace YasserElgammal\Green\Tests\Config;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YasserElgammal\Green\Application;
use YasserElgammal\Green\Config\ConfigCache;
use YasserElgammal\Green\Config\Contracts\ConfigReaderInterface;

final class ApplicationBasePathTest extends TestCase
{
    public function testExplicitBasePathControlsRuntimePaths(): void
    {
        $basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'green-app-' . uniqid('', true);

        $reflection = new ReflectionClass(Application::class);
        $app = $reflection->newInstanceWithoutConstructor();

        $resolvedBasePath = $reflection->getMethod('resolveBasePath')->invoke($app, $basePath);
        $reflection->getProperty('basePath')->setValue($app, $resolvedBasePath);
        $reflection->getMethod('loadConfiguration')->invoke($app, []);
        $config = $app->make(ConfigReaderInterface::class);

        self::assertSame($basePath . '/storage/logs', $config->get('logging.path'));
        self::assertSame(
            $basePath . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'config.php',
            $app->make(ConfigCache::class)->path(),
        );
    }
}
