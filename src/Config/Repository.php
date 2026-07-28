<?php

namespace YasserElgammal\Green\Config;

use YasserElgammal\Green\Config\Contracts\MutableConfigInterface;
use YasserElgammal\Green\Config\Contracts\LockableConfigInterface;
use YasserElgammal\Green\Config\Exceptions\ConfigurationException;

class Repository implements MutableConfigInterface, LockableConfigInterface
{
    protected array $items = [];
    private bool $locked = false;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function merge(array $items): void
    {
        $this->assertMutable();
        $this->items = Merger::merge($this->items, $items);
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

    public function set(string $key, mixed $value): void
    {
        $this->assertMutable();
        $this->assertValidKey($key);
        $keys = explode('.', $key);
        $array = &$this->items;

        foreach ($keys as $i => $segment) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            if (!isset($array[$segment]) || !is_array($array[$segment])) {
                $array[$segment] = [];
            }

            $array = &$array[$segment];
        }

        $array[array_shift($keys)] = $value;
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

    public function lock(): void
    {
        $this->locked = true;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    private function assertMutable(): void
    {
        if ($this->locked) {
            throw new ConfigurationException('Configuration is locked after application boot.');
        }
    }

    private function assertValidKey(string $key): void
    {
        if ($key === '' || str_starts_with($key, '.') || str_ends_with($key, '.') || str_contains($key, '..')) {
            throw new ConfigurationException("Invalid configuration key [{$key}].");
        }
    }
}
