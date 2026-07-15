<?php

namespace YasserElgammal\Green\Routing;

final class RouteRegistrar
{
    public function __construct(
        private readonly RouteRegistry $routes,
    ) {
    }

    /**
     * @param class-string $controllerClass
     */
    public function registerRoutesFromController(string $controllerClass): void
    {
        $reflection = new \ReflectionClass($controllerClass);

        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(Route::class);

            foreach ($attributes as $attribute) {
                /** @var Route $route */
                $route = $attribute->newInstance();
                $this->registerControllerRoute($controllerClass, $method->getName(), $route);
            }
        }
    }

    /**
     * @param class-string $controllerClass
     */
    private function registerControllerRoute(string $controllerClass, string $method, Route $route): void
    {
        $httpMethods = is_array($route->method) ? $route->method : [$route->method];

        foreach ($httpMethods as $httpMethod) {
            $this->routes->add(
                (string) $httpMethod,
                $route->path,
                [$controllerClass, $method],
                $route->middleware,
                $route->name
            );
        }
    }
}