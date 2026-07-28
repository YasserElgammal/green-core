<?php

namespace YasserElgammal\Green\Tests\Config;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Config\Contracts\ConfigDefinitionInterface;
use YasserElgammal\Green\Config\CoreDefinitions;

final class DefinitionRegistryTest extends TestCase
{
    public function test_module_can_extend_configuration_without_changing_application(): void
    {
        $definition = new class implements ConfigDefinitionInterface {
            public function defaults(string $basePath): array
            {
                return ['feature' => ['enabled' => false]];
            }
            public function environmentMap(): array
            {
                return ['feature.enabled' => ['env' => 'FEATURE_ENABLED', 'type' => 'bool']];
            }
        };

        $registry = CoreDefinitions::registry([$definition]);

        self::assertFalse($registry->defaults('/app')['feature']['enabled']);
        self::assertSame('FEATURE_ENABLED', $registry->environmentMap()['feature.enabled']['env']);
    }
}
