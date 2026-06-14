<?php

namespace YasserElgammal\Green\Routing;

abstract class AbstractResolver
{
    /**
     * @var array<string, object|callable>
     */
    protected array $bindings = [];

    /**
     * @var list<string>
     */
    protected array $resolving = [];

    public function bind(string $class, object|callable $instance): void
    {
        $this->bindings[$class] = $instance;
    }

    abstract public function resolve(string $class): object;

    /**
     * @param class-string $class
     */
    protected function build(string $class): object
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

    protected function resolveParameter(string $class, \ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            /** @var class-string $dependencyClass */
            $dependencyClass = $type->getName();

            if (array_key_exists($dependencyClass, $this->bindings)) {
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
