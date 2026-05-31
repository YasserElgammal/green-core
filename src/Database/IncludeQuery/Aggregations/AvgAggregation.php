<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Aggregations;

use YasserElgammal\Green\Database\IncludeQuery\Exceptions\InvalidOperationValueException;

/**
 * Averages a numeric column across related rows.
 *
 * Syntax: avg:column  (column required)
 * SQL:    AVG(column)
 * Result: float (null if no rows)
 */
final class AvgAggregation implements AggregationInterface
{
    public function name(): string
    {
        return 'avg';
    }

    public function requiresColumn(): bool
    {
        return true;
    }

    public function validate(string $value): void
    {
        if ($value === '') {
            throw new InvalidOperationValueException(
                'avg',
                $value,
                "The 'avg' aggregation requires a column name. Use 'avg:column_name'.",
            );
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value)) {
            throw new InvalidOperationValueException(
                'avg',
                $value,
                "Invalid column name '{$value}'. Use alphanumeric characters and underscores.",
            );
        }
    }

    public function sqlExpression(string $column): string
    {
        return "AVG({$column})";
    }

    public function attributeName(string $relation, string $column): string
    {
        return "{$relation}_avg_{$column}";
    }

    public function castResult(mixed $raw): ?float
    {
        return $raw !== null ? (float) $raw : null;
    }
}
