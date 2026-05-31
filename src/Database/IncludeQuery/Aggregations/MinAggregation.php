<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Aggregations;

use YasserElgammal\Green\Database\IncludeQuery\Exceptions\InvalidOperationValueException;

/**
 * Finds the minimum value of a column across related rows.
 *
 * Syntax: min:column  (column required)
 * SQL:    MIN(column)
 * Result: mixed (passthrough — preserves original column type)
 */
final class MinAggregation implements AggregationInterface
{
    public function name(): string
    {
        return 'min';
    }

    public function requiresColumn(): bool
    {
        return true;
    }

    public function validate(string $value): void
    {
        if ($value === '') {
            throw new InvalidOperationValueException(
                'min',
                $value,
                "The 'min' aggregation requires a column name. Use 'min:column_name'.",
            );
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value)) {
            throw new InvalidOperationValueException(
                'min',
                $value,
                "Invalid column name '{$value}'. Use alphanumeric characters and underscores.",
            );
        }
    }

    public function sqlExpression(string $column): string
    {
        return "MIN({$column})";
    }

    public function attributeName(string $relation, string $column): string
    {
        return "{$relation}_min_{$column}";
    }

    public function castResult(mixed $raw): mixed
    {
        return $raw;
    }
}
