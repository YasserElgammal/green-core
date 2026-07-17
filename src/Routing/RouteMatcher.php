<?php

namespace YasserElgammal\Green\Routing;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use YasserElgammal\Green\Http\Request;
use function FastRoute\cachedDispatcher;
use function FastRoute\simpleDispatcher;

final class RouteMatcher
{
    private ?string $routeCacheFile = null;
    private bool $routeCacheDisabled = true;

    public function __construct(
        private readonly RouteRegistry $routes,
    ) {
    }

    public function enableRouteCache(?string $cacheFile = null, bool $disabled = false): static
    {
        $this->routeCacheFile = $cacheFile ?? RouteCache::defaultPath();
        $this->routeCacheDisabled = $disabled;

        return $this;
    }

    public function disableRouteCache(): static
    {
        $this->routeCacheDisabled = true;

        return $this;
    }

    public function cacheRoutes(?string $cacheFile = null): void
    {
        $cacheFile ??= $this->routeCacheFile ?? RouteCache::defaultPath();
        RouteCache::ensureDirectoryExists($cacheFile);

        cachedDispatcher(
            fn(RouteCollector $collector) => $this->registerRoutesIntoCollector($collector),
            [
                'cacheFile' => $cacheFile,
                'cacheDisabled' => false,
            ]
        );
    }

    public function match(Request $request): array
    {
        return $this->makeDispatcher()->dispatch($request->getMethod(), $request->getPath());
    }

    private function makeDispatcher(): Dispatcher
    {
        $dispatcher = null;

        if ($this->routeCacheFile !== null) {
            RouteCache::ensureDirectoryExists($this->routeCacheFile);
            $dispatcher = cachedDispatcher(
                fn(RouteCollector $collector) => $this->registerRoutesIntoCollector($collector),
                [
                    'cacheFile' => $this->routeCacheFile,
                    'cacheDisabled' => $this->routeCacheDisabled,
                ]
            );
        } else {
            $dispatcher = simpleDispatcher(
                fn(RouteCollector $collector) => $this->registerRoutesIntoCollector($collector)
            );
        }

        return $dispatcher;
    }

    private function registerRoutesIntoCollector(RouteCollector $collector): void
    {
        foreach ($this->routes->all() as $route) {
            $collector->addRoute($route['method'], $route['path'], [
                'handler' => $route['handler'],
                'middleware' => $route['middleware'],
            ]);
        }
    }
}