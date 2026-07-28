<?php

namespace YasserElgammal\Green\Config\Sources;

use YasserElgammal\Green\Config\Contracts\ConfigSourceInterface;
use YasserElgammal\Green\Config\Environment;
use YasserElgammal\Green\Config\Exceptions\ConfigurationException;

final readonly class EnvironmentSource implements ConfigSourceInterface
{
    /** @param array<string, array{env:string,type?:string}> $map */
    public function __construct(private array $map)
    {
    }

    public function load(): array
    {
        $items = [];
        foreach ($this->map as $key => $definition) {
            $value = Environment::get($definition['env']);
            if ($value === null || $value === '') {
                continue;
            }
            self::put($items, $key, $this->cast($definition['env'], $value, $definition['type'] ?? 'string'));
        }
        return $items;
    }

    private function cast(string $env, mixed $value, string $type): mixed
    {
        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                ?? throw new ConfigurationException("Environment variable [{$env}] must be boolean."),
            'int' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
                ?? throw new ConfigurationException("Environment variable [{$env}] must be an integer."),
            'float' => is_numeric($value) ? (float) $value
                : throw new ConfigurationException("Environment variable [{$env}] must be numeric."),
            default => (string) $value,
        };
    }

    private static function put(array &$items, string $key, mixed $value): void
    {
        $cursor = &$items;
        foreach (explode('.', $key) as $segment) {
            $cursor[$segment] ??= [];
            $cursor = &$cursor[$segment];
        }
        $cursor = $value;
    }
}
