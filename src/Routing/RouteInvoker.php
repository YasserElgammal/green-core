<?php

namespace YasserElgammal\Green\Routing;

use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use YasserElgammal\Green\Http\Payload;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;

final class RouteInvoker
{
    public function __construct(
        private readonly ControllerResolver $controllerResolver = new ControllerResolver(),
        private readonly ResponseNormalizer $responseNormalizer = new ResponseNormalizer(),
    ) {
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array<string, mixed> $vars
     */
    public function invoke(array $handler, Request $request, array $vars): Response
    {
        [$controllerClass, $method] = $handler;

        $controller = $this->controllerResolver->resolve($controllerClass);
        $reflectionMethod = new ReflectionMethod($controllerClass, $method);
        $arguments = $this->resolveArguments($reflectionMethod, $request, $vars);

        return $this->responseNormalizer->normalize($controller->$method(...$arguments));
    }

    /**
     * @param array<string, mixed> $vars
     * @return list<mixed>
     */
    private function resolveArguments(ReflectionMethod $method, Request $request, array $vars): array
    {
        $arguments = [];

        foreach ($method->getParameters() as $parameter) {
            $arguments[] = $this->resolveParameter($parameter, $request, $vars);
        }

        return $arguments;
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function resolveParameter(ReflectionParameter $parameter, Request $request, array $vars): mixed
    {
        $value = null;
        $resolved = false;

        if (array_key_exists($parameter->getName(), $vars)) {
            $value = $vars[$parameter->getName()];
            $resolved = true;
        } else {
            [$resolved, $value] = $this->resolveTypedParameter($parameter, $request);
        }

        if (!$resolved && $parameter->isDefaultValueAvailable()) {
            $value = $parameter->getDefaultValue();
            $resolved = true;
        }

        if (!$resolved && $parameter->allowsNull()) {
            $resolved = true;
        }

        if (!$resolved) {
            throw new \RuntimeException(
                'Cannot resolve controller action parameter [$' . $parameter->getName() . '] in [' .
                $parameter->getDeclaringFunction()->getName() . '].'
            );
        }

        return $value;
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function resolveTypedParameter(ReflectionParameter $parameter, Request $request): array
    {
        $type = $parameter->getType();
        $value = null;
        $resolved = false;

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();
            $value = match (true) {
                $typeName === Request::class => $request,
                is_subclass_of($typeName, Payload::class) => new $typeName($request),
                default => $this->controllerResolver->resolve($typeName),
            };
            $resolved = true;
        }

        return [$resolved, $value];
    }
}