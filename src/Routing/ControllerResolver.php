<?php

namespace YasserElgammal\Green\Routing;

final class ControllerResolver extends AbstractResolver
{

    /**
     * @param class-string $controllerClass
     */
    public function resolve(string $controllerClass): object
    {
        $controller = $this->bindings[$controllerClass] ?? null;

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
}
