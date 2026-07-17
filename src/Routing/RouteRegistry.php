<?php

namespace YasserElgammal\Green\Routing;

final class RouteRegistry
{
    /** @var array<int, array{method: string, path: string, handler: array{0: class-string, 1: string}, middleware: array<int, string|object>}> */
    private array $routes = [];

    /** @var array<string, string> */
    private array $namedRoutes = [];

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array<int, string|object> $middleware
     */
    public function add(string $method, string $path, array $handler, array $middleware = [], ?string $name = null): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];

        if ($name !== null) {
            $this->namedRoutes[$name] = $path;
        }
    }

    public function getNamedRoute(string $name): ?string
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * @return array<int, array{method: string, path: string, handler: array{0: class-string, 1: string}, middleware: array<int, string|object>}>
     */
    public function all(): array
    {
        return $this->routes;
    }
}