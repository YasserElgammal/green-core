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
        $resolved = null;
        $requiresInstanceCheck = false;

        if (is_object($middleware)) {
            $resolved = $middleware;
        } else {
            [$middleware, $resolved, $requiresInstanceCheck] = $this->resolveAlias($middleware, $parameters);
            $resolved ??= $this->bindings[$middleware] ?? null;

            if ($resolved === null) {
                $resolved = $this->build($middleware);
                $requiresInstanceCheck = true;
            } elseif (is_callable($resolved)) {
                $resolved = $resolved();
                $requiresInstanceCheck = true;
            }

            if ($requiresInstanceCheck && !$resolved instanceof $middleware) {
                throw new \RuntimeException(
                    "Middleware resolver must return an instance of [{$middleware}]."
                );
            }
        }

        return $this->guardHandleMethod($resolved);
    }

    /**
     * @return array{0: string, 1: object|null, 2: bool}
     */
    private function resolveAlias(string $middleware, array $parameters): array
    {
        $resolved = null;
        $requiresInstanceCheck = true;

        if (isset($this->aliases[$middleware])) {
            $alias = $this->aliases[$middleware];

            if (is_callable($alias) && !is_string($alias)) {
                $resolved = $alias(...$parameters);
                $requiresInstanceCheck = false;
            } elseif (is_object($alias)) {
                $resolved = $alias;
                $requiresInstanceCheck = false;
            } else {
                $middleware = $alias;
            }
        }

        return [$middleware, $resolved, $requiresInstanceCheck];
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