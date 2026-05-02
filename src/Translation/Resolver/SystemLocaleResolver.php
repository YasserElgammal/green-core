<?php

namespace YasserElgammal\Green\Translation\Resolver;

use YasserElgammal\Green\Translation\Contracts\LocaleResolverInterface;

/**
 * Resolves locale from environment configuration.
 *
 * Reads $_ENV['APP_LOCALE'] and falls back to a hardcoded default.
 * This is typically the last resolver in a chain, guaranteeing
 * that a locale is always available.
 */
final class SystemLocaleResolver implements LocaleResolverInterface
{
    public function __construct(
        private readonly string $defaultLocale = 'en',
    ) {}

    /** @inheritDoc */
    public function resolve(): ?string
    {
        $envLocale = $_ENV['APP_LOCALE'] ?? null;

        if (is_string($envLocale) && $envLocale !== '') {
            return $envLocale;
        }

        return $this->defaultLocale;
    }
}
