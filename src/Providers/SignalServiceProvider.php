<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\Signal\SignalDispatcher;

class SignalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            SignalDispatcher::class,
            fn () => new SignalDispatcher(fn (string $listener) => $this->app->make($listener)),
        );
    }

}
