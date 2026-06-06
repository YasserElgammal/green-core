<?php

namespace YasserElgammal\Green\Routing;

final class MiddlewareResolver
{
    /**
     * @var array<class-string, object|callable>
     */
    private array $middleware = [];

    /**
     * @var list<class-string>
     */
    private array $resolving = [];

    /**
     * @param class-string $middlewareClass
     */
    public function bind(string $middlewareClass, object|callable $middleware): void
    {
        $this->middleware[$middlewareClass] = $middleware;
    }

    /**
     * @param class-string|object $middleware
     */
    public function resolve(string|object $middleware): object
    {
        if (is_object($middleware)) {
            return $this->guardHandleMethod($middleware);
        }

        $resolved = $this->middleware[$middleware] ?? null;

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

    /**
     * @param class-string $class
     */
    private function build(string $class): object
    {
        if (in_array($class, $this->resolving, true)) {
            throw new \RuntimeException(
                'Circular dependency detected while resolving [' . $class . '].'
            );
        }

        if (!class_exists($class)) {
            throw new \RuntimeException("Class [{$class}] does not exist.");
        }

        $reflection = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new \RuntimeException("Class [{$class}] is not instantiable.");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return new $class();
        }

        $this->resolving[] = $class;

        try {
            $arguments = array_map(
                fn(\ReflectionParameter $parameter) => $this->resolveParameter($class, $parameter),
                $constructor->getParameters()
            );
        } finally {
            array_pop($this->resolving);
        }

        return $reflection->newInstanceArgs($arguments);
    }

    private function resolveParameter(string $class, \ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            /** @var class-string $dependencyClass */
            $dependencyClass = $type->getName();

            if (array_key_exists($dependencyClass, $this->middleware)) {
                return $this->resolve($dependencyClass);
            }

            return $this->build($dependencyClass);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new \RuntimeException(
            'Cannot resolve parameter [$' . $parameter->getName() . '] for [' . $class . ']. ' .
            'Only class-typed constructor dependencies can be auto-wired.'
        );
    }

    private function guardHandleMethod(object $middleware): object
    {
        if (!method_exists($middleware, 'handle')) {
            throw new \RuntimeException(
                'Middleware [' . $middleware::class . '] must define a handle() method.'
            );
        }

        return $middleware;
    }
}
