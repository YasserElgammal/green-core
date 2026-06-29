<?php

namespace YasserElgammal\Green\Routing;

use RuntimeException;
use YasserElgammal\Green\Http\Request;

class UrlGenerator
{
    public function __construct(
        private readonly Router $router,
        private readonly Request $request
    ) {}

    /**
     * Generate an absolute URL for a named route.
     *
     * @param string $name The name of the route
     * @param array $parameters URI parameters to bind
     * @return string
     * @throws RuntimeException If the route is not found or missing parameters
     */
    public function route(string $name, array $parameters = []): string
    {
        $path = $this->router->getNamedRoute($name);

        if ($path === null) {
            throw new RuntimeException("Route [{$name}] not defined.");
        }

        // Replace route parameters (e.g. {id} or {id:\d+})
        $path = preg_replace_callback('/\{([a-zA-Z0-9_]+)(:[^\}]+)?\}/', function ($matches) use (&$parameters, $name) {
            $paramName = $matches[1];
            if (!array_key_exists($paramName, $parameters)) {
                throw new RuntimeException("Missing required parameter [{$paramName}] for route [{$name}].");
            }
            $value = $parameters[$paramName];
            unset($parameters[$paramName]);
            return $value;
        }, $path);

        // Append remaining parameters as query string
        $queryString = '';
        if (!empty($parameters)) {
            $queryString = '?' . http_build_query($parameters);
        }

        return $this->getBaseUrl() . '/' . ltrim($path, '/') . $queryString;
    }

    /**
     * Generate an absolute URL for a path.
     */
    public function to(string $path): string
    {
        return $this->getBaseUrl() . '/' . ltrim($path, '/');
    }

    private function getBaseUrl(): string
    {
        $secure = $this->request->server['HTTPS'] ?? 'off';
        $scheme = ($secure === 'on' || $secure == 1 || $this->request->header('X-Forwarded-Proto') === 'https') ? 'https' : 'http';
        $host = $this->request->header('Host') ?? $this->request->server['SERVER_NAME'] ?? 'localhost';
        return "{$scheme}://{$host}";
    }
}
