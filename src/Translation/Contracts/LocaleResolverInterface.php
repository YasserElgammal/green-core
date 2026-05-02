<?php

namespace YasserElgammal\Green\Translation\Contracts;

/**
 * Resolves the active locale from runtime context.
 *
 * Implementations can inspect HTTP headers, user preferences,
 * session data, environment variables, or any other source.
 * Returning null signals that this resolver cannot determine
 * the locale, allowing the next resolver in the chain to try.
 */
interface LocaleResolverInterface
{
    /**
     * Attempt to resolve the current locale.
     *
     * @return string|null A locale code, or null if undetermined.
     */
    public function resolve(): ?string;
}
