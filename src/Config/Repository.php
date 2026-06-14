<?php

namespace YasserElgammal\Green\Config;

class Repository
{
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
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
        $default = microtime(true);
        return $this->get($key, $default) !== $default;
    }

    public function all(): array
    {
        return $this->items;
    }

    public function loadDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = glob($path . '/*.php');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $this->set($name, require $file);
        }
    }
}
