<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Aggregations;

use YasserElgammal\Green\Database\IncludeQuery\Exceptions\InvalidOperationValueException;

/**
 * Checks whether any related rows exist.
 *
 * Syntax: exists  (no column needed)
 * SQL:    COUNT(*)  (cast to boolean in PHP)
 * Result: boolean
 */
final class ExistsAggregation implements AggregationInterface
{
    public function name(): string
    {
        return 'exists';
    }

    public function requiresColumn(): bool
    {
        return false;
    }

    public function validate(string $value): void
    {
        if ($value !== '') {
            throw new InvalidOperationValueException(
                'exists',
                $value,
                "The 'exists' aggregation does not accept a column. Use 'exists' without a value.",
            );
        }
    }

    public function sqlExpression(string $column): string
    {
        return 'COUNT(*)';
    }

    public function attributeName(string $relation, string $column): string
    {
        return "{$relation}_exists";
    }

    public function castResult(mixed $raw): bool
    {
        return ((int) ($raw ?? 0)) > 0;
    }
}
