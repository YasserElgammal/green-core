<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Operations;

use YasserElgammal\Green\Database\IncludeQuery\Exceptions\UnknownOperationException;

/**
 * Maps operation names to their concrete OperationInterface implementations.
 *
 * Extensible at runtime — register custom operations without modifying core code.
 *
 * Design Pattern: Strategy Registry (same pattern as RelationRegistry)
 */
final class OperationRegistry
{
    /** @var array<string, class-string<OperationInterface>> */
    private static array $operations = [
        'limit'  => LimitOperation::class,
        'order'  => OrderOperation::class,
        'select' => SelectOperation::class,
        'filter' => FilterOperation::class,
        'offset' => OffsetOperation::class,
    ];

    /** @var array<string, OperationInterface> Instance cache */
    private static array $instances = [];

    /**
     * Resolve an operation instance by name.
     *
     * @throws UnknownOperationException
     */
    public static function resolve(string $name, string $relation = ''): OperationInterface
    {
        if (!isset(self::$operations[$name])) {
            throw new UnknownOperationException($name, $relation, self::names());
        }

        // Cache instances — operations are stateless
        if (!isset(self::$instances[$name])) {
            self::$instances[$name] = new self::$operations[$name]();
        }

        return self::$instances[$name];
    }

    /**
     * Check if an operation name is registered.
     */
    public static function has(string $name): bool
    {
        return isset(self::$operations[$name]);
    }

    /**
     * Register a custom operation at runtime.
     *
     * @param  string                             $name       e.g. 'sortBy'
     * @param  class-string<OperationInterface>   $className  Concrete implementation
     *
     * @throws \InvalidArgumentException if the class doesn't implement OperationInterface
     */
    public static function register(string $name, string $className): void
    {
        if (!is_a($className, OperationInterface::class, true)) {
            throw new \InvalidArgumentException(
                "Operation [{$className}] must implement " . OperationInterface::class
            );
        }

        self::$operations[$name] = $className;
        unset(self::$instances[$name]); // Clear cache for this name
    }

    /**
     * List all registered operation names.
     *
     * @return string[]
     */
    public static function names(): array
    {
        return array_keys(self::$operations);
    }

    /**
     * Reset to default operations (useful for testing).
     */
    public static function reset(): void
    {
        self::$operations = [
            'limit'  => LimitOperation::class,
            'order'  => OrderOperation::class,
            'select' => SelectOperation::class,
            'filter' => FilterOperation::class,
            'offset' => OffsetOperation::class,
        ];
        self::$instances = [];
    }
}
