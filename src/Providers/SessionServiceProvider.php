<?php

namespace YasserElgammal\Green\Providers;

use Symfony\Component\HttpFoundation\Session\Session;
use YasserElgammal\Green\Session\SessionManager;
use YasserElgammal\Green\Support\ServiceProvider;

class SessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SessionManager::class, static function (): SessionManager {
            return new SessionManager(new Session());
        });
    }
}
