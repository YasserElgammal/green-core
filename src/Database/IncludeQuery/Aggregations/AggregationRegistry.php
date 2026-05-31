<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Aggregations;

/**
 * Maps aggregation names to their concrete AggregationInterface implementations.
 *
 * Extensible at runtime — register custom aggregations without modifying core code.
 *
 * Design Pattern: Strategy Registry (same pattern as OperationRegistry / RelationRegistry)
 */
final class AggregationRegistry
{
    /** @var array<string, class-string<AggregationInterface>> */
    private static array $aggregations = [
        'count'  => CountAggregation::class,
        'exists' => ExistsAggregation::class,
        'sum'    => SumAggregation::class,
        'avg'    => AvgAggregation::class,
        'min'    => MinAggregation::class,
        'max'    => MaxAggregation::class,
    ];

    /** @var array<string, AggregationInterface> Instance cache */
    private static array $instances = [];

    /**
     * Resolve an aggregation instance by name.
     *
     * @throws \InvalidArgumentException
     */
    public static function resolve(string $name): AggregationInterface
    {
        if (!isset(self::$aggregations[$name])) {
            throw new \InvalidArgumentException(
                "Unknown aggregation [{$name}]. " .
                "Registered aggregations: [" . implode(', ', self::names()) . "]."
            );
        }

        // Cache instances — aggregations are stateless
        if (!isset(self::$instances[$name])) {
            self::$instances[$name] = new self::$aggregations[$name]();
        }

        return self::$instances[$name];
    }

    /**
     * Check if an aggregation name is registered.
     */
    public static function has(string $name): bool
    {
        return isset(self::$aggregations[$name]);
    }

    /**
     * Register a custom aggregation at runtime.
     *
     * @param  string                                $name       e.g. 'median'
     * @param  class-string<AggregationInterface>    $className  Concrete implementation
     *
     * @throws \InvalidArgumentException if the class doesn't implement AggregationInterface
     */
    public static function register(string $name, string $className): void
    {
        if (!is_a($className, AggregationInterface::class, true)) {
            throw new \InvalidArgumentException(
                "Aggregation [{$className}] must implement " . AggregationInterface::class
            );
        }

        self::$aggregations[$name] = $className;
        unset(self::$instances[$name]); // Clear cache for this name
    }

    /**
     * List all registered aggregation names.
     *
     * @return string[]
     */
    public static function names(): array
    {
        return array_keys(self::$aggregations);
    }

    /**
     * Reset to default aggregations (useful for testing).
     */
    public static function reset(): void
    {
        self::$aggregations = [
            'count'  => CountAggregation::class,
            'exists' => ExistsAggregation::class,
            'sum'    => SumAggregation::class,
            'avg'    => AvgAggregation::class,
            'min'    => MinAggregation::class,
            'max'    => MaxAggregation::class,
        ];
        self::$instances = [];
    }
}
