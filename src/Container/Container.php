<?php

namespace YasserElgammal\Green\Container;

use ReflectionClass;
use ReflectionParameter;
use ReflectionNamedType;

class Container
{
    protected static ?Container $instance = null;

    /**
     * @var array<string, array{concrete: object|callable|string, shared: bool}>
     */
    protected array $bindings = [];

    /**
     * @var array<string, object>
     */
    protected array $instances = [];

    /**
     * @var array<string, bool>
     */
    protected array $buildStack = [];

    public function bind(string $abstract, object|callable|string|null $concrete = null, bool $shared = false): void
    {
        $concrete ??= $abstract;

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared'   => $shared,
        ];
    }

    public function singleton(string $abstract, object|callable|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function make(string $abstract): mixed
    {
        return $this->resolve($abstract);
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    protected function resolve(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->bindings[$abstract]['concrete'] ?? $abstract;

        if ($concrete === $abstract || $concrete instanceof \Closure) {
            $object = $this->build($concrete);
        } else {
            $object = $this->make($concrete);
        }

        if (isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared'] === true) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    protected function build(object|callable|string $concrete): mixed
    {
        if ($concrete instanceof \Closure) {
            return $concrete($this);
        }

        if (!is_string($concrete)) {
            return $concrete;
        }

        if (isset($this->buildStack[$concrete])) {
            throw new BindingException("Circular dependency detected while resolving [{$concrete}].");
        }

        if (!class_exists($concrete)) {
            throw new NotFoundException("Target class [{$concrete}] does not exist.");
        }

        $this->buildStack[$concrete] = true;

        $reflection = new ReflectionClass($concrete);

        if (!$reflection->isInstantiable()) {
            unset($this->buildStack[$concrete]);
            throw new BindingException("Target class [{$concrete}] is not instantiable.");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            unset($this->buildStack[$concrete]);
            return new $concrete();
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters, $concrete);

        unset($this->buildStack[$concrete]);

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * @param ReflectionParameter[] $parameters
     */
    protected function resolveDependencies(array $parameters, string $concrete): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependencies[] = $this->resolvePrimitive($parameter, $concrete);
        }

        return $dependencies;
    }

    protected function resolvePrimitive(ReflectionParameter $parameter, string $concrete): mixed
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->make($type->getName());
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new BindingException(
            "Unresolvable dependency resolving [\${$parameter->getName()}] in class {$concrete}"
        );
    }

    public static function setInstance(Container $container): void
    {
        static::$instance = $container;
    }

    public static function getInstance(): ?Container
    {
        return static::$instance;
    }
}
