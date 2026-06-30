<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\Routing\Router;
use YasserElgammal\Green\Routing\RouteCache;
use YasserElgammal\Green\Routing\RateLimiter;
use YasserElgammal\Green\Routing\RateLimit\ArrayRateLimitStore;
use YasserElgammal\Green\Routing\RateLimit\FileRateLimitStore;
use YasserElgammal\Green\Routing\RateLimit\RateLimitStoreInterface;
use YasserElgammal\Green\Http\Middleware\ThrottleRequests;

class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RateLimitStoreInterface::class, function ($app) {
            $config = $app->make('config');
            $driver = $config->get('rate_limit.driver', 'file');

            if ($driver === 'array') {
                return new ArrayRateLimitStore();
            }

            $basePath = defined('BASE_PATH') ? rtrim(constant('BASE_PATH'), '/\\') : (getcwd() ?: '.');
            $path = $config->get('rate_limit.path', $basePath . DIRECTORY_SEPARATOR . 'storage/framework/cache/rate-limit');

            return new FileRateLimitStore($path);
        });

        $this->app->singleton(RateLimiter::class, fn($app) => new RateLimiter(
            $app->make(RateLimitStoreInterface::class)
        ));
        $this->app->singleton(Router::class, function ($app) {
            return new Router();
        });
    }

    public function boot(): void
    {
        $router = $this->app->make(Router::class);
        $config = $this->app->make('config');

        $router->aliasMiddleware('throttle', function (?string $maxAttempts = null, ?string $decayMinutes = null, ?string $prefix = null) use ($config) {
            return new ThrottleRequests(
                $this->app->make(RateLimiter::class),
                (int) ($maxAttempts ?? $config->get('rate_limit.defaults.max_attempts', 60)),
                (int) ($decayMinutes ?? $config->get('rate_limit.defaults.decay_minutes', 1)),
                $prefix
            );
        });
        if ($config->get('app.route_cache', false)) {
            $cacheFile = $config->get('app.route_cache_file', RouteCache::defaultPath());
            $router->enableRouteCache($cacheFile);
        }
    }
}
