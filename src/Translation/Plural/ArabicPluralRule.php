<?php

namespace YasserElgammal\Green\Translation\Plural;

use YasserElgammal\Green\Translation\Contracts\PluralRuleInterface;

/**
 * Arabic pluralization rule (full CLDR specification).
 *
 * Arabic has six plural categories based on the integer value:
 *
 *   - "zero"  → n = 0
 *   - "one"   → n = 1
 *   - "two"   → n = 2
 *   - "few"   → n % 100 in 3..10
 *   - "many"  → n % 100 in 11..99
 *   - "other" → everything else (including 100, 200, etc.)
 *
 * Example translation entry:
 *   {
 *     "zero":  "لا عناصر",
 *     "one":   "عنصر واحد",
 *     "two":   "عنصران",
 *     "few":   ":count عناصر",
 *     "many":  ":count عنصرًا",
 *     "other": ":count عنصر"
 *   }
 */
final class ArabicPluralRule implements PluralRuleInterface
{
    /** @inheritDoc */
    public function choose(int $count): string
    {
        $abs = abs($count);

        if ($abs === 0) {
            return 'zero';
        }

        if ($abs === 1) {
            return 'one';
        }

        if ($abs === 2) {
            return 'two';
        }

        $mod100 = $abs % 100;

        if ($mod100 >= 3 && $mod100 <= 10) {
            return 'few';
        }

        if ($mod100 >= 11 && $mod100 <= 99) {
            return 'many';
        }

        return 'other';
    }

    /** @inheritDoc */
    public function getLocale(): string
    {
        return 'ar';
    }
}
