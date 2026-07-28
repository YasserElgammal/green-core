<?php

namespace YasserElgammal\Green\Config\Sources;

use YasserElgammal\Green\Config\Contracts\ConfigSourceInterface;

final readonly class ArraySource implements ConfigSourceInterface
{
    public function __construct(private array $items)
    {
    }

    public function load(): array
    {
        return $this->items;
    }
}
