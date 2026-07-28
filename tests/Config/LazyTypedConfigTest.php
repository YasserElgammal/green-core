<?php

namespace YasserElgammal\Green\Tests\Config;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YasserElgammal\Green\Application;
use YasserElgammal\Green\Config\Typed\MailConfig;

final class LazyTypedConfigTest extends TestCase
{
    public function testTypedConfigIsBuiltOnceOnFirstResolution(): void
    {
        $application = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'green-lazy-config-' . uniqid('', true);

        $reflection = new ReflectionClass($application);
        $reflection->getProperty('basePath')->setValue($application, $basePath);
        $reflection->getMethod('loadConfiguration')->invoke($application, []);

        self::assertTrue($application->has(MailConfig::class));
        self::assertFalse($this->hasResolvedInstance($application, MailConfig::class));

        $first = $application->make(MailConfig::class);

        self::assertTrue($this->hasResolvedInstance($application, MailConfig::class));
        self::assertSame($first, $application->make(MailConfig::class));
    }

    private function hasResolvedInstance(Application $application, string $abstract): bool
    {
        $container = new ReflectionClass($application);
        $instances = $container->getParentClass()->getProperty('instances')->getValue($application);

        return isset($instances[$abstract]);
    }
}
