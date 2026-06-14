<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\Routing\Router;
use YasserElgammal\Green\Routing\RouteCache;

class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Router::class, function ($app) {
            return new Router();
        });
    }

    public function boot(): void
    {
        $router = $this->app->make(Router::class);
        $config = $this->app->make('config');

        if ($config->get('app.route_cache', false)) {
            $cacheFile = $config->get('app.route_cache_file', RouteCache::defaultPath());
            $router->enableRouteCache($cacheFile);
        }
    }
}
