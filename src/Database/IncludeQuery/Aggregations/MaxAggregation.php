<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Aggregations;

use YasserElgammal\Green\Database\IncludeQuery\Exceptions\InvalidOperationValueException;

/**
 * Finds the maximum value of a column across related rows.
 *
 * Syntax: max:column  (column required)
 * SQL:    MAX(column)
 * Result: mixed (passthrough — preserves original column type)
 */
final class MaxAggregation implements AggregationInterface
{
    public function name(): string
    {
        return 'max';
    }

    public function requiresColumn(): bool
    {
        return true;
    }

    public function validate(string $value): void
    {
        if ($value === '') {
            throw new InvalidOperationValueException(
                'max',
                $value,
                "The 'max' aggregation requires a column name. Use 'max:column_name'.",
            );
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value)) {
            throw new InvalidOperationValueException(
                'max',
                $value,
                "Invalid column name '{$value}'. Use alphanumeric characters and underscores.",
            );
        }
    }

    public function sqlExpression(string $column): string
    {
        return "MAX({$column})";
    }

    public function attributeName(string $relation, string $column): string
    {
        return "{$relation}_max_{$column}";
    }

    public function castResult(mixed $raw): mixed
    {
        return $raw;
    }
}
