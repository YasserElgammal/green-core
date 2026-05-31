<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Aggregations;

use YasserElgammal\Green\Database\IncludeQuery\Exceptions\InvalidOperationValueException;

/**
 * Sums a numeric column across related rows.
 *
 * Syntax: sum:column  (column required)
 * SQL:    SUM(column)
 * Result: float
 */
final class SumAggregation implements AggregationInterface
{
    public function name(): string
    {
        return 'sum';
    }

    public function requiresColumn(): bool
    {
        return true;
    }

    public function validate(string $value): void
    {
        if ($value === '') {
            throw new InvalidOperationValueException(
                'sum',
                $value,
                "The 'sum' aggregation requires a column name. Use 'sum:column_name'.",
            );
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value)) {
            throw new InvalidOperationValueException(
                'sum',
                $value,
                "Invalid column name '{$value}'. Use alphanumeric characters and underscores.",
            );
        }
    }

    public function sqlExpression(string $column): string
    {
        return "SUM({$column})";
    }

    public function attributeName(string $relation, string $column): string
    {
        return "{$relation}_sum_{$column}";
    }

    public function castResult(mixed $raw): float
    {
        return (float) ($raw ?? 0);
    }
}
