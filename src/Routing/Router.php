<?php

namespace YasserElgammal\Green\Routing;

use FastRoute\RouteCollector;
use FastRoute\Dispatcher;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Http\JsonResponse;
use function FastRoute\simpleDispatcher;

class Router
{
    protected array $routes = [];
    protected array $globalMiddleware = [];

    public function addGlobalMiddleware(string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
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
        $dispatcher = simpleDispatcher(function (RouteCollector $r) {
            foreach ($this->routes as $route) {
                $r->addRoute($route['method'], $route['path'], [
                    'handler' => $route['handler'],
                    'middleware' => $route['middleware']
                ]);
            }
        });

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

    protected function runPipeline(array $middlewares, Request $request, array $handler, array $vars): Response
    {
        $pipeline = function ($req) use ($handler, $vars) {
            $controllerClass = $handler[0];
            $method = $handler[1];
            $controller = new $controllerClass();

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

        foreach (array_reverse($middlewares) as $middlewareClass) {
            $next = $pipeline;
            $pipeline = function ($req) use ($middlewareClass, $next) {
                $middleware = new $middlewareClass();
                return $middleware->handle($req, $next);
            };
        }

        return $pipeline($request);
    }
}
