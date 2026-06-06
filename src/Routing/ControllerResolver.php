<?php

namespace YasserElgammal\Green\Routing;

final class ControllerResolver
{
    /**
     * @var array<class-string, object|callable>
     */
    private array $controllers = [];

    /**
     * @var list<class-string>
     */
    private array $resolving = [];

    /**
     * @param class-string $controllerClass
     */
    public function bind(string $controllerClass, object|callable $controller): void
    {
        $this->controllers[$controllerClass] = $controller;
    }

    /**
     * @param class-string $controllerClass
     */
    public function resolve(string $controllerClass): object
    {
        $controller = $this->controllers[$controllerClass] ?? null;

        if ($controller === null) {
            return $this->build($controllerClass);
        }

        if (is_callable($controller)) {
            $controller = $controller();
        }

        if (!$controller instanceof $controllerClass) {
            throw new \RuntimeException(
                "Controller resolver must return an instance of [{$controllerClass}]."
            );
        }

        return $controller;
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

            if (array_key_exists($dependencyClass, $this->controllers)) {
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
}
