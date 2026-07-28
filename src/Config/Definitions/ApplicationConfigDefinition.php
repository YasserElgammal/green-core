<?php

namespace YasserElgammal\Green\Config\Definitions;

use YasserElgammal\Green\Config\Contracts\ConfigDefinitionInterface;

final class ApplicationConfigDefinition implements ConfigDefinitionInterface
{
    public function defaults(string $basePath): array
    {
        return ['app' => ['debug' => false, 'locale' => 'en', 'fallback_locale' => 'en', 'providers' => []]];
    }

    public function environmentMap(): array
    {
        return [
            'app.debug' => ['env' => 'APP_DEBUG', 'type' => 'bool'],
            'app.locale' => ['env' => 'APP_LOCALE'],
            'app.fallback_locale' => ['env' => 'APP_FALLBACK_LOCALE'],
        ];
    }

}
