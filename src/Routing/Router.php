<?php

namespace YasserElgammal\Green\Routing;

use FastRoute\RouteCollector;
use FastRoute\Dispatcher;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Http\JsonResponse;
use function FastRoute\cachedDispatcher;
use function FastRoute\simpleDispatcher;

class Router
{
    protected array $routes = [];
    protected array $globalMiddleware = [];
    private ?string $routeCacheFile = null;
    private bool $routeCacheDisabled = true;

    public function __construct(
        private readonly ControllerResolver $controllerResolver = new ControllerResolver(),
        private readonly MiddlewareResolver $middlewareResolver = new MiddlewareResolver(),
    ) {
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

                $httpMethods = is_array($route->method) ? $route->method : [$route->method];
                foreach ($httpMethods as $httpMethod) {
                    $this->routes[] = [
                        'method' => strtoupper($httpMethod),
                        'path' => $route->path,
                        'handler' => [$controllerClass, $method->getName()],
                        'middleware' => $route->middleware
                    ];
                }
            }
        }
    }

    public function dispatch(Request $request): Response
    {
        $dispatcher = $this->makeDispatcher();

        $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getPath());

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                return new Response('404 Not Found', 404);
            case Dispatcher::METHOD_NOT_ALLOWED:
                return new Response('405 Method Not Allowed', 405);
            case Dispatcher::FOUND:
                $handlerInfo = $routeInfo[1];
                $vars = $routeInfo[2];
                $handler = $handlerInfo['handler'];
                $routeMiddleware = $handlerInfo['middleware'];

                foreach ($vars as $key => $value) {
                    $request->setAttribute($key, $value);
                }

                $middlewares = array_merge($this->globalMiddleware, $routeMiddleware);
                return $this->runPipeline($middlewares, $request, $handler, $vars);
        }

        return new Response('500 Internal Server Error', 500);
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
                'middleware' => $route['middleware']
            ]);
        }
    }

    protected function runPipeline(array $middlewares, Request $request, array $handler, array $vars): Response
    {
        $pipeline = function ($req) use ($handler, $vars) {
            $controllerClass = $handler[0];
            $method = $handler[1];
            $controller = $this->controllerResolver->resolve($controllerClass);

            $reflectionMethod = new \ReflectionMethod($controllerClass, $method);
            $args = [];
            foreach ($reflectionMethod->getParameters() as $param) {
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $typeName = $type->getName();
                    if ($typeName === Request::class) {
                        $args[] = $req;
                    } elseif (is_subclass_of($typeName, \YasserElgammal\Green\Http\Payload::class)) {
                        $args[] = new $typeName($req);
                    } else {
                        $args[] = null;
                    }
                } elseif (array_key_exists($param->getName(), $vars)) {
                    $args[] = $vars[$param->getName()];
                } else {
                    $args[] = null;
                }
            }

            $response = $controller->$method(...$args);

            if (is_array($response)) {
                return new JsonResponse($response);
            }
            if ($response instanceof Response) {
                return $response;
            }
            if (is_string($response) || is_numeric($response)) {
                return new Response((string)$response);
            }

            return new Response('', 200);
        };

        foreach (array_reverse($middlewares) as $middlewareItem) {
            $next = $pipeline;
            $pipeline = function ($req) use ($middlewareItem, $next) {
                $middleware = $this->middlewareResolver->resolve($middlewareItem);
                return $middleware->handle($req, $next);
            };
        }

        return $pipeline($request);
    }
}
