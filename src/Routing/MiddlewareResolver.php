<?php

namespace YasserElgammal\Green\Routing;

use YasserElgammal\Green\Middleware\MiddlewareInterface;

final class MiddlewareResolver extends AbstractResolver
{
    /** @var array<string, class-string|object|callable> */
    protected array $aliases = [];

    public function alias(string $name, string|object|callable $middleware): void
    {
        $this->aliases[$name] = $middleware;
    }

    /**
     * @param class-string|object $middleware
     */
    public function resolve(string|object $middleware, array $parameters = []): object
    {
        if (is_object($middleware)) {
            return $this->guardHandleMethod($middleware);
        }

        if (isset($this->aliases[$middleware])) {
            $alias = $this->aliases[$middleware];

            if (is_callable($alias) && !is_string($alias)) {
                return $this->guardHandleMethod($alias(...$parameters));
            }

            if (is_object($alias)) {
                return $this->guardHandleMethod($alias);
            }

            $middleware = $alias;
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