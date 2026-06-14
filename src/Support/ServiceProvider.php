<?php

namespace YasserElgammal\Green\Support;

use YasserElgammal\Green\Application;

abstract class ServiceProvider
{
    public function __construct(protected Application $app)
    {
    }

    /**
     * Register any application services into the container.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}
