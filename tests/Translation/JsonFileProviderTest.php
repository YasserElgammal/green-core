<?php

namespace YasserElgammal\Green\Tests\Translation;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Translation\Context\TranslationContext;
use YasserElgammal\Green\Translation\Provider\JsonFileProvider;

final class JsonFileProviderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/green_translations_' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/en', 0777, true);
        file_put_contents($this->directory . '/en/messages.json', json_encode([
            'welcome' => 'Welcome',
            'orders' => ['pending' => 'Pending'],
            'items' => ['one' => 'One item', 'other' => ':count items'],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->directory . '/en/checkout.json', json_encode([
            'title' => 'Checkout',
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/en/*.json') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory . '/en');
        rmdir($this->directory);
    }

    public function test_provider_resolves_nested_contextual_and_missing_keys(): void
    {
        $provider = new JsonFileProvider($this->directory);

        self::assertSame('Pending', $provider->get('messages.orders.pending', 'en'));
        self::assertSame('Checkout', $provider->get('title', 'en', new TranslationContext(feature: 'checkout')));
        self::assertTrue($provider->has('messages.welcome', 'en'));
        self::assertNull($provider->get('messages.missing', 'en'));
        self::assertSame([], $provider->all('missing'));
    }

    public function test_all_flattens_nested_values_but_preserves_plural_sets(): void
    {
        $translations = (new JsonFileProvider($this->directory))->all('en');

        self::assertSame('Pending', $translations['messages.orders.pending']);
        self::assertSame(
            ['one' => 'One item', 'other' => ':count items'],
            $translations['messages.items'],
        );
    }
}
