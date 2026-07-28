<?php

namespace YasserElgammal\Green\Config\Contracts;

interface MutableConfigInterface extends ConfigReaderInterface
{
    public function set(string $key, mixed $value): void;
    public function merge(array $items): void;
}
