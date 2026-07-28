<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\Cache\CacheManager;
use YasserElgammal\Green\Config\Typed\CacheConfig;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CacheManager::class, function ($app) {
            $settings = $app->make(CacheConfig::class);
            return new CacheManager($settings->toArray());
        });
    }

}
