<?php

namespace YasserElgammal\Green\Config\Definitions;

use YasserElgammal\Green\Config\Contracts\ConfigDefinitionInterface;

final class InfrastructureConfigDefinition implements ConfigDefinitionInterface
{
    public function defaults(string $basePath): array
    {
        return [
            'logging' => ['path' => $basePath . '/storage/logs'],
            'translation' => [
                'default_locale' => 'en', 'fallback_locale' => 'en',
                'lang_path' => 'lang', 'cache_path' => null,
            ],
            'view' => [
                'path' => $basePath . '/views', 'cache' => false,
                'cache_path' => $basePath . '/storage/cache/views',
            ],
            'cache' => [
                'default' => 'file',
                'stores' => [
                    'file' => ['driver' => 'file', 'path' => $basePath . '/storage/cache'],
                    'array' => ['driver' => 'array'],
                ],
            ],
        ];
    }

    public function environmentMap(): array
    {
        return [
            'logging.path' => ['env' => 'LOG_DIR'],
            'translation.default_locale' => ['env' => 'APP_LOCALE'],
            'translation.fallback_locale' => ['env' => 'APP_FALLBACK_LOCALE'],
            'translation.lang_path' => ['env' => 'APP_LANG_PATH'],
            'translation.cache_path' => ['env' => 'APP_TRANSLATION_CACHE_PATH'],
        ];
    }

}
