<?php

namespace YasserElgammal\Green\Routing;

use YasserElgammal\Green\Http\Middleware\MiddlewareInterface;

final class MiddlewareResolver extends AbstractResolver
{

    /**
     * @param class-string|object $middleware
     */
    public function resolve(string|object $middleware): object
    {
        if (is_object($middleware)) {
            return $this->guardHandleMethod($middleware);
        }

        $resolved = $this->bindings[$middleware] ?? null;

        if ($resolved === null) {
            return $this->guardHandleMethod($this->build($middleware));
        }

        if (is_callable($resolved)) {
            $resolved = $resolved();
        }

        if (!$resolved instanceof $middleware) {
            throw new \RuntimeException(
                "Middleware resolver must return an instance of [{$middleware}]."
            );
        }

        return $this->guardHandleMethod($resolved);
    }

    private function guardHandleMethod(object $middleware): object
    {
        if (!$middleware instanceof MiddlewareInterface) {
            if (!method_exists($middleware, 'handle')) {
                throw new \RuntimeException(
                    'Middleware [' . $middleware::class . '] must implement MiddlewareInterface.'
                );
            }
            @trigger_error(
                'Middleware [' . $middleware::class . '] should implement MiddlewareInterface. '
                . 'Duck-typing is deprecated and will be removed in v3.0.',
                E_USER_DEPRECATED
            );
        }

        return $middleware;
    }
}
