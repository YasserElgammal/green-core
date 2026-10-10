<?php

namespace YasserElgammal\Green\Config;

use YasserElgammal\Green\Config\Contracts\ConfigReaderInterface;

class Repository implements ConfigReaderInterface
{
    public function __construct(private readonly array $items = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        if (!str_contains($key, '.')) {
            return $default;
        }

        $array = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    public function has(string $key): bool
    {
        $sentinel = new \stdClass();
        return $this->get($key, $sentinel) !== $sentinel;
    }

    public function all(): array
    {
        return $this->items;
    }
}
