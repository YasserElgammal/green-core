<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Config\Typed\TranslationConfig;
use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\Translation\Translator;
use YasserElgammal\Green\Translation\TranslatorManager;

final class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Translator::class, function ($app) {
            $settings = $app->make(TranslationConfig::class);

            return TranslatorManager::create([
                'default_locale' => $settings->defaultLocale,
                'fallback_locale' => $settings->fallbackLocale,
                'lang_path' => $settings->langPath,
                'cache_path' => $settings->cachePath,
            ]);
        });
    }

    public function boot(): void
    {
        TranslatorManager::setResolver(fn () => $this->app->make(Translator::class));
    }
}
