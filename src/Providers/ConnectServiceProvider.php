<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\Connect\ConnectManager;
use YasserElgammal\Green\Connect\Connect;

class ConnectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectManager::class, function ($app) {
            $config = $app->make('config')->get('connect', []);
            return new ConnectManager($config);
        });

        $this->app->singleton(Connect::class, function ($app) {
            return new Connect($app->make(ConnectManager::class));
        });
    }

    public function boot(): void
    {
        connect_set_instance($this->app->make(Connect::class));
    }
}
