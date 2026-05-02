<?php

namespace YasserElgammal\Green\Translation\Plural;

use YasserElgammal\Green\Translation\Contracts\PluralRuleInterface;

/**
 * English pluralization rule.
 *
 * CLDR categories:
 *   - "one"   → count is exactly 1
 *   - "other" → everything else
 */
final class EnglishPluralRule implements PluralRuleInterface
{
    /** @inheritDoc */
    public function choose(int $count): string
    {
        return $count === 1 ? 'one' : 'other';
    }

    /** @inheritDoc */
    public function getLocale(): string
    {
        return 'en';
    }
}
