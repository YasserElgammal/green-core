<?php

namespace YasserElgammal\Green\Routing;

use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;

final class MiddlewarePipeline
{
    public function __construct(
        private readonly MiddlewareResolver $middlewareResolver = new MiddlewareResolver(),
    ) {
    }

    /**
     * @param array<int, string|object> $middlewares
     * @param callable(Request): Response $destination
     */
    public function send(Request $request, array $middlewares, callable $destination): Response
    {
        $pipeline = $destination;

        foreach (array_reverse($middlewares) as $middlewareItem) {
            $next = $pipeline;
            $pipeline = function (Request $request) use ($middlewareItem, $next): Response {
                [$name, $parameters] = $this->resolveMiddlewareItem($middlewareItem);
                $middleware = $this->middlewareResolver->resolve($name, $parameters);

                return $middleware->handle($request, $next);
            };
        }

        return $pipeline($request);
    }

    /**
     * @return array{0: string|object, 1: array<int, string>}
     */
    private function resolveMiddlewareItem(string|object $middleware): array
    {
        if (is_object($middleware)) {
            return [$middleware, []];
        }

        if (!str_contains($middleware, ':')) {
            return [$middleware, []];
        }

        [$name, $parameterString] = explode(':', $middleware, 2);
        $parameters = $parameterString === '' ? [] : array_map('trim', explode(',', $parameterString));

        return [$name, $parameters];
    }
}
