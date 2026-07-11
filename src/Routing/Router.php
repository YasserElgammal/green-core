<?php

namespace YasserElgammal\Green\Routing;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use function FastRoute\cachedDispatcher;
use function FastRoute\simpleDispatcher;

class Router
{
    protected array $routes = [];
    protected array $namedRoutes = [];
    protected array $globalMiddleware = [];
    private ?string $routeCacheFile = null;
    private bool $routeCacheDisabled = true;

    public function __construct(
        private readonly ControllerResolver $controllerResolver = new ControllerResolver(),
        private readonly MiddlewareResolver $middlewareResolver = new MiddlewareResolver(),
        private readonly ?RouteInvoker $routeInvoker = null,
        private readonly ?MiddlewarePipeline $middlewarePipeline = null,
    ) {
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
        $this->routeCacheFile = $cacheFile ?? RouteCache::defaultPath();
        $this->routeCacheDisabled = $disabled;

        return $this;
    }

    public function disableRouteCache(): static
    {
        $this->routeCacheDisabled = true;

        return $this;
    }

    public function getNamedRoute(string $name): ?string
    {
        return $this->namedRoutes[$name] ?? null;
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

    public function registerRoutesFromController(string $controllerClass): void
    {
        $reflection = new \ReflectionClass($controllerClass);
        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(Route::class);
            foreach ($attributes as $attribute) {
                /** @var Route $route */
                $route = $attribute->newInstance();

                if ($route->name) {
                    $this->namedRoutes[$route->name] = $route->path;
                }

                $httpMethods = is_array($route->method) ? $route->method : [$route->method];
                foreach ($httpMethods as $httpMethod) {
                    $this->routes[] = [
                        'method' => strtoupper($httpMethod),
                        'path' => $route->path,
                        'handler' => [$controllerClass, $method->getName()],
                        'middleware' => $route->middleware,
                    ];
                }
            }
        }
    }

    public function dispatch(Request $request): Response
    {
        $dispatcher = $this->makeDispatcher();
        $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getPath());
        $response = null;

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                $response = new Response('404 Not Found', 404);
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $response = new Response('405 Method Not Allowed', 405);
                break;
            case Dispatcher::FOUND:
                $response = $this->dispatchMatchedRoute($request, $routeInfo[1], $routeInfo[2]);
                break;
            default:
                $response = new Response('500 Internal Server Error', 500);
        }

        return $response;
    }

    private function makeDispatcher(): Dispatcher
    {
        if ($this->routeCacheFile !== null) {
            RouteCache::ensureDirectoryExists($this->routeCacheFile);

            return cachedDispatcher(
                fn(RouteCollector $collector) => $this->registerRoutesIntoCollector($collector),
                [
                    'cacheFile' => $this->routeCacheFile,
                    'cacheDisabled' => $this->routeCacheDisabled,
                ]
            );
        }

        return simpleDispatcher(
            fn(RouteCollector $collector) => $this->registerRoutesIntoCollector($collector)
        );
    }

    private function registerRoutesIntoCollector(RouteCollector $collector): void
    {
        foreach ($this->routes as $route) {
            $collector->addRoute($route['method'], $route['path'], [
                'handler' => $route['handler'],
                'middleware' => $route['middleware'],
            ]);
        }
    }

    /**
     * @param array{handler: array{0: class-string, 1: string}, middleware: array<int, string|object>} $handlerInfo
     * @param array<string, mixed> $vars
     */
    private function dispatchMatchedRoute(Request $request, array $handlerInfo, array $vars): Response
    {
        $handler = $handlerInfo['handler'];

        foreach ($vars as $key => $value) {
            $request->setAttribute($key, $value);
        }

        $request->setAttribute('__green_route_handler', $handler);
        $request->setAttribute('__green_route_vars', $vars);

        $middlewares = array_merge(
            $this->globalMiddleware,
            $handlerInfo['middleware'],
            [new PolicyMiddleware()]
        );

        return $this->runPipeline($middlewares, $request, $handler, $vars);
    }

    /**
     * @param array<int, string|object> $middlewares
     * @param array{0: class-string, 1: string} $handler
     * @param array<string, mixed> $vars
     */
    protected function runPipeline(array $middlewares, Request $request, array $handler, array $vars): Response
    {
        return $this->pipeline()->send(
            $request,
            $middlewares,
            fn(Request $request): Response => $this->invoker()->invoke($handler, $request, $vars)
        );
    }

    private function invoker(): RouteInvoker
    {
        return $this->routeInvoker ?? new RouteInvoker($this->controllerResolver);
    }

    private function pipeline(): MiddlewarePipeline
    {
        return $this->middlewarePipeline ?? new MiddlewarePipeline($this->middlewareResolver);
    }
}
