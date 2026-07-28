<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\ErrorHandling\GreenErrorKernel;
use YasserElgammal\Green\Exceptions\ExceptionHandler;
use YasserElgammal\Green\Logging\LogManager;
use YasserElgammal\Green\Config\Typed\ApplicationConfig;

class ErrorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GreenErrorKernel::class, function ($app) {
            return new GreenErrorKernel($app->make(LogManager::class));
        });

        $this->app->singleton(ExceptionHandler::class, function ($app) {
            return new ExceptionHandler(
                $app->make(LogManager::class),
                $app->make(ApplicationConfig::class),
            );
        });
    }

    public function boot(): void
    {
        $this->app->make(GreenErrorKernel::class)->register();
    }
}
