<?php

namespace YasserElgammal\Green\Config\Contracts;

interface ConfigReaderInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function has(string $key): bool;
    public function all(): array;
}
