<?php

namespace YasserElgammal\Green\Translation;

use YasserElgammal\Green\Translation\Contracts\LocaleResolverInterface;
use YasserElgammal\Green\Translation\Contracts\TranslationCacheInterface;
use YasserElgammal\Green\Translation\Context\TranslationContext;
use YasserElgammal\Green\Translation\Plural\PluralRuleFactory;

/**
 * The main translation service — single entry point for the engine.
 *
 * Combines locale resolution, provider fallback, caching,
 * interpolation, and pluralization into a clean, injectable API.
 *
 * Usage:
 *   $translator->get('messages.welcome', ['name' => 'Yasser']);
 *   $translator->choice('items.count', 5, ['count' => 5]);
 *
 * This class is intentionally presentation-agnostic: it returns
 * plain strings and can be consumed by any layer (API, CLI,
 * templates, queue workers) without coupling.
 */
final class Translator
{
    /** Runtime locale override (set via setLocale). */
    private ?string $currentLocale = null;

    /**
     * @param FallbackChain               $fallbackChain  Resolves translations through providers + locale cascade.
     * @param LocaleResolverInterface      $localeResolver Determines the active locale.
     * @param Interpolator                 $interpolator   Variable substitution engine.
     * @param PluralRuleFactory            $pluralFactory  Provides language-specific plural rules.
     * @param TranslationCacheInterface|null $cache         Optional caching layer.
     * @param string                       $defaultLocale  Ultimate default locale.
     * @param string|null                  $fallbackLocale Intermediate fallback locale.
     */
    public function __construct(
        private readonly FallbackChain $fallbackChain,
        private readonly LocaleResolverInterface $localeResolver,
        private readonly Interpolator $interpolator,
        private readonly PluralRuleFactory $pluralFactory,
        private readonly ?TranslationCacheInterface $cache = null,
        private readonly string $defaultLocale = 'en',
        private readonly ?string $fallbackLocale = null,
    ) {}

    /**
     * Translate a key.
     *
     * @param string                  $key     Dot-notation translation key.
     * @param array<string,mixed>     $replace Interpolation replacements.
     * @param string|null             $locale  Override locale (null = auto-resolve).
     * @param TranslationContext|null $context Optional module/feature scope.
     *
     * @return string The translated, interpolated string.
     */
    public function get(
        string $key,
        array $replace = [],
        ?string $locale = null,
        ?TranslationContext $context = null,
    ): string {
        $locale = $locale ?? $this->getLocale();

        // 1. Check cache.
        $cacheKey = $this->buildCacheKey($key, $context);

        if ($this->cache !== null && $this->cache->has($cacheKey, $locale)) {
            $value = $this->cache->get($cacheKey, $locale);

            if (is_string($value)) {
                return $this->interpolator->interpolate($value, $replace);
            }
        }

        // 2. Resolve through fallback chain.
        $value = $this->fallbackChain->resolve($key, $locale, $context);

        // 3. If the resolved value is an array (plural set), pick "other" or first.
        if (is_array($value)) {
            $value = $value['other'] ?? reset($value) ?: $key;
        }

        // 4. Cache the result.
        if ($this->cache !== null && is_string($value)) {
            $this->cache->set($cacheKey, $locale, $value);
        }

        // 5. Interpolate variables.
        return is_string($value)
            ? $this->interpolator->interpolate($value, $replace)
            : $key;
    }

    /**
     * Translate a key with pluralization.
     *
     * The translation value must be an array keyed by CLDR categories:
     *   {"one": "1 item", "other": ":count items"}
     *
     * @param string                  $key     Dot-notation translation key.
     * @param int                     $count   The count to pluralize by.
     * @param array<string,mixed>     $replace Interpolation replacements.
     * @param string|null             $locale  Override locale.
     * @param TranslationContext|null $context Optional scope.
     *
     * @return string The pluralized, interpolated string.
     */
    public function choice(
        string $key,
        int $count,
        array $replace = [],
        ?string $locale = null,
        ?TranslationContext $context = null,
    ): string {
        $locale = $locale ?? $this->getLocale();

        // Resolve the raw value (should be an array of plural forms).
        $cacheKey = $this->buildCacheKey($key, $context);
        $value    = null;

        if ($this->cache !== null && $this->cache->has($cacheKey, $locale)) {
            $value = $this->cache->get($cacheKey, $locale);
        }

        if ($value === null) {
            $value = $this->fallbackChain->resolve($key, $locale, $context);

            if ($this->cache !== null) {
                $this->cache->set($cacheKey, $locale, $value);
            }
        }

        // If the value is a pipe-delimited string (e.g. "1 item|:count items"),
        // convert it to a simple one/other array.
        if (is_string($value) && str_contains($value, '|')) {
            $parts = explode('|', $value);
            $value = match (count($parts)) {
                2 => ['one' => $parts[0], 'other' => $parts[1]],
                3 => ['one' => $parts[0], 'few' => $parts[1], 'other' => $parts[2]],
                default => ['other' => $parts[array_key_last($parts)]],
            };
        }

        // Select the plural form.
        if (is_array($value)) {
            $rule     = $this->pluralFactory->get($locale);
            $category = $rule->choose($count);

            $selected = $value[$category] ?? $value['other'] ?? reset($value) ?: $key;
        } else {
            $selected = is_string($value) ? $value : $key;
        }

        // Always include :count in replacements.
        $replace['count'] = $count;

        return $this->interpolator->interpolate($selected, $replace);
    }

    /**
     * Check whether a translation key exists.
     */
    public function has(
        string $key,
        ?string $locale = null,
        ?TranslationContext $context = null,
    ): bool {
        $locale = $locale ?? $this->getLocale();

        return $this->fallbackChain->has($key, $locale, $context);
    }

    /**
     * Get the current active locale.
     */
    public function getLocale(): string
    {
        if ($this->currentLocale !== null) {
            return $this->currentLocale;
        }

        return $this->localeResolver->resolve() ?? $this->defaultLocale;
    }

    /**
     * Override the locale for the current runtime.
     */
    public function setLocale(string $locale): void
    {
        $this->currentLocale = $locale;
    }

    /**
     * Reset the locale to auto-resolution.
     */
    public function resetLocale(): void
    {
        $this->currentLocale = null;
    }

    /**
     * Get the configured default locale.
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Get the configured fallback locale.
     */
    public function getFallbackLocale(): ?string
    {
        return $this->fallbackLocale;
    }

    /**
     * Flush the translation cache.
     */
    public function flushCache(?string $locale = null): void
    {
        $this->cache?->flush($locale);
    }

    /**
     * Build a composite cache key incorporating context dimensions.
     */
    private function buildCacheKey(string $key, ?TranslationContext $context = null): string
    {
        if ($context === null || $context->isEmpty()) {
            return $key;
        }

        return $context->toCacheKey() . '::' . $key;
    }
}
