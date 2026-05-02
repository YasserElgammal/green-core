<?php

namespace YasserElgammal\Green\Translation\Plural;

use YasserElgammal\Green\Translation\Contracts\PluralRuleInterface;

/**
 * Factory that returns the correct pluralization rule for a locale.
 *
 * Ships with English and Arabic rules out of the box.
 * Additional rules can be registered at runtime via register().
 *
 * When a locale has no dedicated rule, the factory falls back to
 * the base language (e.g. "ar_EG" → "ar"), and ultimately to a
 * simple "one vs. other" default.
 */
final class PluralRuleFactory
{
    /**
     * @var array<string, PluralRuleInterface>
     */
    private array $rules = [];

    public function __construct()
    {
        // Register built-in rules.
        $this->register(new EnglishPluralRule());
        $this->register(new ArabicPluralRule());
    }

    /**
     * Register a custom pluralization rule.
     *
     * Overwrites any existing rule for the same locale.
     */
    public function register(PluralRuleInterface $rule): void
    {
        $this->rules[$rule->getLocale()] = $rule;
    }

    /**
     * Get the pluralization rule for a locale.
     *
     * Falls back to the base language code if a regional variant
     * is not registered (e.g. "en_GB" → "en").
     */
    public function get(string $locale): PluralRuleInterface
    {
        // Exact match.
        if (isset($this->rules[$locale])) {
            return $this->rules[$locale];
        }

        // Try base locale (e.g. "ar_EG" → "ar").
        $base = strtok($locale, '_-');

        if ($base !== false && isset($this->rules[$base])) {
            return $this->rules[$base];
        }

        // Fallback: simple "one / other" rule.
        return new EnglishPluralRule();
    }

    /**
     * Check whether a dedicated rule exists for a locale.
     */
    public function has(string $locale): bool
    {
        if (isset($this->rules[$locale])) {
            return true;
        }

        $base = strtok($locale, '_-');

        return $base !== false && isset($this->rules[$base]);
    }
}
