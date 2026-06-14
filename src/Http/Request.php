<?php

namespace YasserElgammal\Green\Http;

use YasserElgammal\Green\Session\SessionManager;

class Request
{
    public array $query;
    public array $post;
    public array $server;
    public array $files;
    public array $cookies;
    public array $attributes = [];

    public function __construct(
        array $query = [],
        array $post = [],
        array $server = [],
        array $files = [],
        array $cookies = []
    ) {
        $this->query = $query;
        $this->post = $post;
        $this->server = $server;
        $this->files = $files;
        $this->cookies = $cookies;
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_FILES, $_COOKIE);
    }

    public function getMethod(): string
    {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }

    public function getPath(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $position = strpos($uri, '?');
        if ($position !== false) {
            return substr($uri, 0, $position);
        }
        return $uri;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        if (isset($this->server[$key])) {
            return $this->server[$key];
        }

        // PHP exposes these two without the HTTP_ prefix
        $fallback = strtoupper(str_replace('-', '_', $name));
        return $this->server[$fallback] ?? $default;
    }

    private ?array $jsonBody = null;

    public function session(): SessionManager
    {
        return session();
    }

    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json() ?? []);
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function except(array $keys): array
    {
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }

    public function isJson(): bool
    {
        $contentType = $this->header('Content-Type', '');
        return str_contains($contentType, '/json') || str_contains($contentType, '+json');
    }

    public function wantsJson(): bool
    {
        $accept = $this->header('Accept', '');
        return str_contains($accept, '/json') || str_contains($accept, '+json');
    }

    public function ip(): string
    {
        return $this->header('X-Forwarded-For') 
            ?? $this->header('X-Real-IP') 
            ?? $this->server['REMOTE_ADDR'] 
            ?? '127.0.0.1';
    }

    public function fullUrl(): string
    {
        $secure = $this->server['HTTPS'] ?? 'off';
        $scheme = ($secure === 'on' || $secure == 1 || $this->header('X-Forwarded-Proto') === 'https') ? 'https' : 'http';
        $host = $this->header('Host') ?? $this->server['SERVER_NAME'] ?? 'localhost';
        $uri = $this->server['REQUEST_URI'] ?? '/';
        return "{$scheme}://{$host}{$uri}";
    }

    public function isMethod(string $method): bool
    {
        return strtoupper($this->getMethod()) === strtoupper($method);
    }

    public function json(?string $key = null): mixed
    {
        if ($this->jsonBody === null) {
            $content = file_get_contents('php://input');
            $this->jsonBody = json_decode((string) $content, true) ?? [];
        }

        if ($key === null) {
            return $this->jsonBody;
        }

        return $this->jsonBody[$key] ?? null;
    }
}
