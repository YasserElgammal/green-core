<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\Connect\ConnectManager;
use YasserElgammal\Green\Config\Contracts\ConfigReaderInterface;
use YasserElgammal\Green\Connect\Connect;

class ConnectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectManager::class, function ($app) {
            $config = $app->make(ConfigReaderInterface::class)->get('connect', []);
            return new ConnectManager($config);
        });

        $this->app->singleton(Connect::class, function ($app) {
            return new Connect($app->make(ConnectManager::class));
        });
    }

}
