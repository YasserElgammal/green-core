<?php

namespace YasserElgammal\Green\Config\Typed;

use YasserElgammal\Green\Config\Contracts\ConfigReaderInterface;

final readonly class ApplicationConfig
{
    /** @param list<class-string> $providers */
    public function __construct(
        public bool $debug,
        public string $locale,
        public string $fallbackLocale,
        public array $providers,
    ) {
    }

    public static function fromRepository(ConfigReaderInterface $config): self
    {
        return new self(
            (bool) $config->get('app.debug', false),
            (string) $config->get('app.locale', 'en'),
            (string) $config->get('app.fallback_locale', 'en'),
            (array) $config->get('app.providers', []),
        );
    }
}
