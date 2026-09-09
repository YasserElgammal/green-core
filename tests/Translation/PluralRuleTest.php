<?php

namespace YasserElgammal\Green\Tests\Translation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Translation\Plural\ArabicPluralRule;
use YasserElgammal\Green\Translation\Plural\EnglishPluralRule;
use YasserElgammal\Green\Translation\Plural\PluralRuleFactory;

final class PluralRuleTest extends TestCase
{
    #[DataProvider('arabicCounts')]
    public function test_arabic_cldr_categories(int $count, string $category): void
    {
        self::assertSame($category, (new ArabicPluralRule())->choose($count));
    }

    public static function arabicCounts(): array
    {
        return [[0, 'zero'], [1, 'one'], [2, 'two'], [7, 'few'], [15, 'many'], [100, 'other'], [-3, 'few']];
    }

    public function test_factory_resolves_regional_locales_and_falls_back_to_english(): void
    {
        $factory = new PluralRuleFactory();

        self::assertInstanceOf(ArabicPluralRule::class, $factory->get('ar_EG'));
        self::assertTrue($factory->has('en-GB'));
        self::assertFalse($factory->has('fr'));
        self::assertInstanceOf(EnglishPluralRule::class, $factory->get('fr'));
    }
}
