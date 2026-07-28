<?php

namespace YasserElgammal\Green\Config\Typed;

use YasserElgammal\Green\Config\Contracts\ConfigReaderInterface;

final readonly class TranslationConfig
{
    public function __construct(
        public string $defaultLocale,
        public string $fallbackLocale,
        public string $langPath,
        public ?string $cachePath,
    ) {
    }

    public static function fromRepository(ConfigReaderInterface $config, string $basePath): self
    {
        return new self(
            (string) $config->get('translation.default_locale', 'en'),
            (string) $config->get('translation.fallback_locale', 'en'),
            self::absolute((string) $config->get('translation.lang_path', 'lang'), $basePath),
            self::nullableAbsolute($config->get('translation.cache_path'), $basePath),
        );
    }

    private static function nullableAbsolute(mixed $path, string $basePath): ?string
    {
        return $path === null || $path === '' ? null : self::absolute((string) $path, $basePath);
    }

    private static function absolute(string $path, string $basePath): string
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
            ? $path
            : $basePath . DIRECTORY_SEPARATOR . $path;
    }
}
