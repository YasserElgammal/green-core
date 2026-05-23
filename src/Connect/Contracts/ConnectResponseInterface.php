<?php

namespace YasserElgammal\Green\Connect\Contracts;

interface ConnectResponseInterface
{
    public function status(): int;

    public function body(): string;

    public function json(?string $key = null, mixed $default = null): mixed;

    public function header(string $name, ?string $default = null): ?string;

    /**
     * @return array<string,string>
     */
    public function headers(): array;

    public function successful(): bool;

    public function failed(): bool;
}
