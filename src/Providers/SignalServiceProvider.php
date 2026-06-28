<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\Signal\SignalDispatcher;

class SignalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SignalDispatcher::class, function ($app) {
            return new SignalDispatcher();
        });
    }

    public function boot(): void
    {
        $dispatcher = $this->app->make(SignalDispatcher::class);

        // Register the global helper instance
        if (function_exists('signal_set_instance')) {
            signal_set_instance($dispatcher);
        }
    }
}
