<?php

namespace YasserElgammal\Green\Translation;

use YasserElgammal\Green\Translation\Contracts\TranslationProviderInterface;
use YasserElgammal\Green\Translation\Context\TranslationContext;

/**
 * Implements the smart fallback resolution chain.
 *
 * Resolution order for a requested locale such as "ar_EG":
 *
 *   1. All providers are queried for "ar_EG"
 *   2. All providers are queried for the base locale "ar"
 *   3. All providers are queried for the configured fallback locale (e.g. "en")
 *   4. All providers are queried for the default locale (e.g. "en")
 *   5. The raw key itself is returned as a last resort
 *
 * At each level the providers are tried in registration order,
 * so higher-priority sources (e.g. database overrides) are checked
 * before lower-priority ones (e.g. JSON files).
 */
final class FallbackChain
{
    /**
     * @param TranslationProviderInterface[] $providers       Ordered list of providers (highest priority first).
     * @param string                         $defaultLocale   Ultimate default locale.
     * @param string|null                    $fallbackLocale  Optional intermediate fallback locale.
     */
    public function __construct(
        private readonly array $providers,
        private readonly string $defaultLocale = 'en',
        private readonly ?string $fallbackLocale = null,
    ) {}

    /**
     * Resolve a translation by walking the fallback chain.
     *
     * @return string|array<string,mixed> The resolved value, or the key itself.
     */
    public function resolve(
        string $key,
        string $locale,
        ?TranslationContext $context = null,
    ): string|array {
        // Build the ordered list of locales to try.
        $locales = $this->buildLocaleChain($locale);

        foreach ($locales as $tryLocale) {
            foreach ($this->providers as $provider) {
                $value = $provider->get($key, $tryLocale, $context);

                if ($value !== null) {
                    return $value;
                }
            }
        }

        // Ultimate fallback: return the key itself.
        return $key;
    }

    /**
     * Check whether any provider in any fallback locale has this key.
     */
    public function has(
        string $key,
        string $locale,
        ?TranslationContext $context = null,
    ): bool {
        $locales = $this->buildLocaleChain($locale);

        foreach ($locales as $tryLocale) {
            foreach ($this->providers as $provider) {
                if ($provider->has($key, $tryLocale, $context)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Build a de-duplicated, ordered list of locales to query.
     *
     * @return string[]
     */
    private function buildLocaleChain(string $requestedLocale): array
    {
        $chain = [];

        // 1. Requested locale (e.g. "ar_EG")
        $chain[] = $requestedLocale;

        // 2. Base locale if the requested locale is regional (e.g. "ar" from "ar_EG")
        $baseLocale = $this->extractBaseLocale($requestedLocale);
        if ($baseLocale !== null && $baseLocale !== $requestedLocale) {
            $chain[] = $baseLocale;
        }

        // 3. Configured fallback locale
        if ($this->fallbackLocale !== null) {
            $chain[] = $this->fallbackLocale;
        }

        // 4. Default locale
        $chain[] = $this->defaultLocale;

        // De-duplicate while preserving order.
        return array_values(array_unique($chain));
    }

    /**
     * Extract the language part from a regional locale code.
     *
     * "ar_EG" → "ar",  "en" → null (no region to strip)
     */
    private function extractBaseLocale(string $locale): ?string
    {
        if (str_contains($locale, '_') || str_contains($locale, '-')) {
            return strtok($locale, '_-') ?: null;
        }

        return null;
    }
}
