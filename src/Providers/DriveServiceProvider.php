<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\Drive\DriveManager;
use YasserElgammal\Green\Drive\Drive;

class DriveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DriveManager::class, function ($app) {
            $config = $app->make('config')->get('drive', []);
            return new DriveManager($config);
        });

        $this->app->singleton(Drive::class, function ($app) {
            return new Drive($app->make(DriveManager::class));
        });
    }

    public function boot(): void
    {
        drive_set_instance($this->app->make(Drive::class));
    }
}
