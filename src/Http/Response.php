<?php

namespace YasserElgammal\Green\Http;

class Response
{
    public function __construct(
        protected mixed $content = '',
        protected int $statusCode = 200,
        protected array $headers = []
    ) {
    }

    public function setStatusCode(int $code): static
    {
        $this->statusCode = $code;
        return $this;
    }

    public function setHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setContent(mixed $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContent(): mixed
    {
        return $this->content;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    public function withHeader(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }
        echo $this->content;
    }
}
