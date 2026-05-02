<?php

namespace YasserElgammal\Green\Translation\Contracts;

use YasserElgammal\Green\Translation\Context\TranslationContext;

/**
 * Strategy interface for translation sources.
 *
 * Each provider encapsulates a single translation source
 * (file system, database, remote API, etc.) and exposes a
 * uniform read API so the Translator can query them in
 * priority order without knowing the underlying storage.
 */
interface TranslationProviderInterface
{
    /**
     * Retrieve a single translation value.
     *
     * @param string                  $key     Dot-notation key (e.g. "orders.status.pending")
     * @param string                  $locale  ISO locale code (e.g. "en", "ar", "ar_EG")
     * @param TranslationContext|null $context Optional module/feature/scope context
     *
     * @return string|array<string,mixed>|null  The translated value, a plural array, or null when the key does not exist in this provider.
     */
    public function get(string $key, string $locale, ?TranslationContext $context = null): string|array|null;

    /**
     * Check whether a translation exists for the given key.
     */
    public function has(string $key, string $locale, ?TranslationContext $context = null): bool;

    /**
     * Bulk-load every translation for a locale (used by the caching layer).
     *
     * @return array<string, string|array<string,mixed>>
     */
    public function all(string $locale, ?TranslationContext $context = null): array;
}
