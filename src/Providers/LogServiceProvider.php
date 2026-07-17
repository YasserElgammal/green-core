<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\Logging\LogManager;
use YasserElgammal\Green\Logging\Drivers\FileLogger;

class LogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LogManager::class, function ($app) {
            $logDir = $_ENV['LOG_DIR'] ?? dirname(__DIR__, 3) . '/storage/logs';
            $manager = new LogManager();
            $manager->addDriver(new FileLogger($logDir));
            return $manager;
        });
    }

}
