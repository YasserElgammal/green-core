<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\Cache\CacheManager;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CacheManager::class, function ($app) {
            $config = $app->make('config')->get('cache', [
                'default' => 'file',
                'stores' => [
                    'file' => [
                        'driver' => 'file',
                        'path' => rtrim(defined('BASE_PATH') ? constant('BASE_PATH') : getcwd(), '/\\') . '/storage/cache',
                    ],
                    'array' => [
                        'driver' => 'array',
                    ]
                ]
            ]);

            return new CacheManager($config);
        });
    }

    public function boot(): void
    {
        $manager = $this->app->make(CacheManager::class);

        // Register the global helper instance
        if (function_exists('cache_set_instance')) {
            cache_set_instance($manager);
        }
    }
}
