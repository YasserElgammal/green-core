<?php

namespace YasserElgammal\Green\Translation\Resolver;

use YasserElgammal\Green\Translation\Contracts\LocaleResolverInterface;

/**
 * Resolves the configured system locale.
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
        return $this->defaultLocale;
    }
}
