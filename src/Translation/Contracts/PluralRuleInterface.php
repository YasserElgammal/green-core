<?php

namespace YasserElgammal\Green\Translation\Contracts;

/**
 * Language-specific pluralization logic.
 *
 * Each implementation encodes the CLDR plural categories for
 * a single language (or language family).  The Translator
 * uses this to select the correct plural form from a keyed
 * translation array without coupling to any UI framework.
 */
interface PluralRuleInterface
{
    /**
     * Return the CLDR plural category for the given count.
     *
     * Standard categories: "zero", "one", "two", "few", "many", "other".
     */
    public function choose(int $count): string;

    /**
     * The locale code this rule applies to (e.g. "en", "ar").
     */
    public function getLocale(): string;
}
