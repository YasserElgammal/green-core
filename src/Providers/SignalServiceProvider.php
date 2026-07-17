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

}
