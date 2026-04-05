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

    public function session(): SessionManager
    {
        return session();
    }
}
