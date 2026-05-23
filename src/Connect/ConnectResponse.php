<?php

namespace YasserElgammal\Green\Connect;

use YasserElgammal\Green\Connect\Contracts\ConnectResponseInterface;
use YasserElgammal\Green\Connect\Exceptions\RequestException;
use YasserElgammal\Green\Connect\Support\Headers;

final class ConnectResponse implements ConnectResponseInterface
{
    /**
     * @param array<string,string|string[]> $headers
     * @param array<string,mixed> $info
     */
    public function __construct(
        private readonly int $status,
        private readonly string $body = '',
        private readonly array $headers = [],
        private readonly array $info = [],
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        $decoded = json_decode($this->body, true);

        if ($key === null) {
            return $decoded;
        }

        if (!is_array($decoded)) {
            return $default;
        }

        return $decoded[$key] ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return Headers::get($this->headers(), $name, $default);
    }

    /**
     * @return array<string,string>
     */
    public function headers(): array
    {
        return Headers::normalize($this->headers);
    }

    /**
     * @return array<string,mixed>
     */
    public function info(): array
    {
        return $this->info;
    }

    public function ok(): bool
    {
        return $this->status === 200;
    }

    public function created(): bool
    {
        return $this->status === 201;
    }

    public function noContent(): bool
    {
        return $this->status === 204;
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function redirect(): bool
    {
        return $this->status >= 300 && $this->status < 400;
    }

    public function clientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    public function serverError(): bool
    {
        return $this->status >= 500;
    }

    public function failed(): bool
    {
        return $this->status >= 400;
    }

    public function throw(): self
    {
        if ($this->failed()) {
            throw new RequestException($this);
        }

        return $this;
    }
}
