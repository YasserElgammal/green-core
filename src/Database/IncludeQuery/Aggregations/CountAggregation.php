<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Aggregations;

use YasserElgammal\Green\Database\IncludeQuery\Exceptions\InvalidOperationValueException;

/**
 * Counts the number of related rows.
 *
 * Syntax: count  (no column needed)
 * SQL:    COUNT(*)
 * Result: integer
 */
final class CountAggregation implements AggregationInterface
{
    public function name(): string
    {
        return 'count';
    }

    public function requiresColumn(): bool
    {
        return false;
    }

    public function validate(string $value): void
    {
        if ($value !== '') {
            throw new InvalidOperationValueException(
                'count',
                $value,
                "The 'count' aggregation does not accept a column. Use 'count' without a value.",
            );
        }
    }

    public function sqlExpression(string $column): string
    {
        return 'COUNT(*)';
    }

    public function attributeName(string $relation, string $column): string
    {
        return "{$relation}_count";
    }

    public function castResult(mixed $raw): int
    {
        return (int) ($raw ?? 0);
    }
}
