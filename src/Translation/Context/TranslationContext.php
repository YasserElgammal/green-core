<?php

namespace YasserElgammal\Green\Translation\Context;

/**
 * Immutable value object that scopes a translation lookup.
 *
 * Context allows the same translation key to resolve differently
 * depending on the module, feature, or runtime scope, enabling
 * fine-grained overrides without key-name collisions.
 *
 * Examples:
 *   new TranslationContext(module: 'orders')
 *   new TranslationContext(module: 'orders', feature: 'checkout', scope: 'admin')
 */
final class TranslationContext
{
    public function __construct(
        /** Logical module name (e.g. "orders", "users", "payments"). */
        public readonly ?string $module = null,

        /** Feature within a module (e.g. "checkout", "refunds"). */
        public readonly ?string $feature = null,

        /** Runtime scope (e.g. "admin", "api", "cli"). */
        public readonly ?string $scope = null,
    ) {}

    /**
     * Build a cache-safe string key from the context dimensions.
     */
    public function toCacheKey(): string
    {
        return implode('.', array_filter([
            $this->scope,
            $this->module,
            $this->feature,
        ]));
    }

    /**
     * Derive the translation group/file name from the context.
     *
     * Priority: feature → module → null (i.e. use the key's own group).
     */
    public function toGroup(): ?string
    {
        return $this->feature ?? $this->module;
    }

    /**
     * Check whether this context carries any scoping information.
     */
    public function isEmpty(): bool
    {
        return $this->module === null
            && $this->feature === null
            && $this->scope === null;
    }
}
