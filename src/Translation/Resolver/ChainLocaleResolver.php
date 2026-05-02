<?php

namespace YasserElgammal\Green\Translation\Resolver;

use YasserElgammal\Green\Translation\Contracts\LocaleResolverInterface;

/**
 * Chains multiple locale resolvers in priority order.
 *
 * Iterates through the registered resolvers and returns the
 * first non-null result.  This is the primary resolver passed
 * to the Translator — it composes all context-specific resolvers.
 *
 * Usage:
 *   $resolver = new ChainLocaleResolver([
 *       new RequestLocaleResolver(),          // 1st: HTTP context
 *       new UserLocaleResolver($callback),    // 2nd: user preference
 *       new SystemLocaleResolver(),           // 3rd: system default
 *   ]);
 */
final class ChainLocaleResolver implements LocaleResolverInterface
{
    /**
     * @param LocaleResolverInterface[] $resolvers Ordered from highest to lowest priority.
     */
    public function __construct(
        private readonly array $resolvers,
    ) {}

    /** @inheritDoc */
    public function resolve(): ?string
    {
        foreach ($this->resolvers as $resolver) {
            $locale = $resolver->resolve();

            if ($locale !== null) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * Create a new chain with an additional resolver prepended.
     */
    public function prepend(LocaleResolverInterface $resolver): self
    {
        return new self([$resolver, ...$this->resolvers]);
    }

    /**
     * Create a new chain with an additional resolver appended.
     */
    public function append(LocaleResolverInterface $resolver): self
    {
        return new self([...$this->resolvers, $resolver]);
    }
}
