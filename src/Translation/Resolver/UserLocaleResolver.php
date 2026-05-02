<?php

namespace YasserElgammal\Green\Translation\Resolver;

use YasserElgammal\Green\Translation\Contracts\LocaleResolverInterface;

/**
 * Resolves locale from a user object or callback.
 *
 * The callable should return the user's preferred locale code
 * (e.g. from a database column, session, or JWT claim), or null
 * if no authenticated user is available.
 *
 * Usage:
 *   new UserLocaleResolver(fn() => $currentUser?->locale)
 */
final class UserLocaleResolver implements LocaleResolverInterface
{
    /**
     * @param callable(): ?string $userLocaleCallback Returns the user's locale or null.
     */
    public function __construct(
        private $userLocaleCallback,
    ) {}

    /** @inheritDoc */
    public function resolve(): ?string
    {
        $locale = ($this->userLocaleCallback)();

        if (is_string($locale) && $locale !== '') {
            return $locale;
        }

        return null;
    }
}
