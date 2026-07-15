<?php

namespace YasserElgammal\Green\Routing;

use FastRoute\Dispatcher;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;

final class MatchedRouteDispatcher
{
    public function __construct(
        private readonly RouteInvoker $routeInvoker,
        private readonly MiddlewarePipeline $middlewarePipeline,
    ) {
    }

    /**
     * @param array<int, mixed> $routeInfo
     * @param array<int, string|object> $globalMiddleware
     */
    public function dispatch(Request $request, array $routeInfo, array $globalMiddleware = []): Response
    {
        $response = null;

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                $response = new Response('404 Not Found', 404);
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $response = new Response('405 Method Not Allowed', 405);
                break;
            case Dispatcher::FOUND:
                $response = $this->dispatchMatchedRoute($request, $routeInfo[1], $routeInfo[2], $globalMiddleware);
                break;
            default:
                $response = new Response('500 Internal Server Error', 500);
        }

        return $response;
    }

    /**
     * @param array{handler: array{0: class-string, 1: string}, middleware: array<int, string|object>} $handlerInfo
     * @param array<string, mixed> $vars
     * @param array<int, string|object> $globalMiddleware
     */
    private function dispatchMatchedRoute(Request $request, array $handlerInfo, array $vars, array $globalMiddleware): Response
    {
        $handler = $handlerInfo['handler'];

        foreach ($vars as $key => $value) {
            $request->setAttribute($key, $value);
        }

        $request->setAttribute('__green_route_handler', $handler);
        $request->setAttribute('__green_route_vars', $vars);

        $middlewares = array_merge(
            $globalMiddleware,
            $handlerInfo['middleware'],
            [new PolicyMiddleware()]
        );

        return $this->middlewarePipeline->send(
            $request,
            $middlewares,
            fn(Request $request): Response => $this->routeInvoker->invoke($handler, $request, $vars)
        );
    }
}