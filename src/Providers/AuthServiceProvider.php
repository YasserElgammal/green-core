<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\Auth\Authorizer;
use YasserElgammal\Green\Auth\Auth;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Auth::class, fn () => new Auth());
        $this->app->singleton(Authorizer::class, function ($app) {
            return new Authorizer();
        });
    }

}
