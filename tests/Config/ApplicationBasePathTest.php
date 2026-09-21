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

    public function testConfigHelperExposesOnlyReadOperations(): void
    {
        $reflection = new ReflectionClass(Application::class);
        $app = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('basePath')->setValue($app, dirname(__DIR__, 2));
        $reflection->getMethod('loadConfiguration')->invoke($app, []);

        $hadApplication = array_key_exists('__green_app', $GLOBALS);
        $previousApplication = $GLOBALS['__green_app'] ?? null;
        $GLOBALS['__green_app'] = $app;

        try {
            $config = config();

            self::assertInstanceOf(ConfigReaderInterface::class, $config);
            self::assertFalse(method_exists($config, 'set'));
            self::assertFalse(method_exists($config, 'merge'));
            self::assertFalse(method_exists($config, 'lock'));
            self::assertFalse(method_exists($config, 'isLocked'));
        } finally {
            if ($hadApplication) {
                $GLOBALS['__green_app'] = $previousApplication;
            } else {
                unset($GLOBALS['__green_app']);
            }
        }
    }
}
