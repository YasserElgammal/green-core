<?php

namespace YasserElgammal\Green\Routing;

use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;

class Router
{
    /** @var array<int, string|object> */
    protected array $globalMiddleware = [];

    private readonly RouteRegistry $routes;
    private readonly RouteRegistrar $registrar;
    private readonly RouteMatcher $matcher;
    private readonly MatchedRouteDispatcher $dispatcher;

    public function __construct(
        private readonly ControllerResolver $controllerResolver = new ControllerResolver(),
        private readonly MiddlewareResolver $middlewareResolver = new MiddlewareResolver(),
        ?RouteInvoker $routeInvoker = null,
        ?MiddlewarePipeline $middlewarePipeline = null,
        ?RouteRegistry $routeRegistry = null,
        ?RouteRegistrar $routeRegistrar = null,
        ?RouteMatcher $routeMatcher = null,
        ?MatchedRouteDispatcher $matchedRouteDispatcher = null,
    ) {
        $this->routes = $routeRegistry ?? new RouteRegistry();
        $this->registrar = $routeRegistrar ?? new RouteRegistrar($this->routes);
        $this->matcher = $routeMatcher ?? new RouteMatcher($this->routes);
        $this->dispatcher = $matchedRouteDispatcher ?? new MatchedRouteDispatcher(
            $routeInvoker ?? new RouteInvoker($this->controllerResolver),
            $middlewarePipeline ?? new MiddlewarePipeline($this->middlewareResolver)
        );
    }

    public function aliasMiddleware(string $name, string|object|callable $middleware): void
    {
        $this->middlewareResolver->alias($name, $middleware);
    }

    public function addGlobalMiddleware(string|object $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function enableRouteCache(?string $cacheFile = null, bool $disabled = false): static
    {
        $this->matcher->enableRouteCache($cacheFile, $disabled);

        return $this;
    }

    public function disableRouteCache(): static
    {
        $this->matcher->disableRouteCache();

        return $this;
    }

    public function getNamedRoute(string $name): ?string
    {
        return $this->routes->getNamedRoute($name);
    }

    public function cacheRoutes(?string $cacheFile = null): void
    {
        $this->matcher->cacheRoutes($cacheFile);
    }

    /**
     * @param class-string $controllerClass
     */
    public function registerRoutesFromController(string $controllerClass): void
    {
        $this->registrar->registerRoutesFromController($controllerClass);
    }

    public function dispatch(Request $request): Response
    {
        return $this->dispatcher->dispatch(
            $request,
            $this->matcher->match($request),
            $this->globalMiddleware
        );
    }
}