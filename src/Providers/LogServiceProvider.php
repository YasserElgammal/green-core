<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\Logging\LogManager;
use YasserElgammal\Green\Logging\Drivers\FileLogger;
use YasserElgammal\Green\Config\Typed\LoggingConfig;

class LogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LogManager::class, function ($app) {
            $logDir = $app->make(LoggingConfig::class)->path;
            $manager = new LogManager();
            $manager->addDriver(new FileLogger($logDir));
            return $manager;
        });
    }

}
