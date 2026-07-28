<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\Database\ConnectionPool;
use YasserElgammal\Green\Database\Database;
use YasserElgammal\Green\Config\Typed\DatabaseConfig;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectionPool::class, function ($app) {
            return new ConnectionPool($app->make(DatabaseConfig::class)->toArray());
        });
    }

    public function boot(): void
    {
        $pool = $this->app->make(ConnectionPool::class);
        Database::setPool($pool);
    }
}
