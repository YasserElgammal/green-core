<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Operations;

use Doctrine\DBAL\Query\QueryBuilder;
use YasserElgammal\Green\Database\IncludeQuery\Exceptions\InvalidOperationValueException;

/**
 * Controls the sort order of a relation's results.
 *
 * Syntax variants:
 *   order:asc          → ORDER BY id ASC   (defaults to primary key)
 *   order:desc         → ORDER BY id DESC
 *   order:column|asc   → ORDER BY column ASC  (explicit column)
 *   order:column|desc  → ORDER BY column DESC
 *
 * The pipe separator avoids ambiguity with commas in the operation list.
 */
final class OrderOperation implements OperationInterface
{
    private const VALID_DIRECTIONS = ['asc', 'desc'];

    public function name(): string
    {
        return 'order';
    }

    public function validate(string $value): void
    {
        $parts = explode('|', $value);

        if (count($parts) === 1) {
            // Simple direction: order:desc
            if (!in_array(strtolower($parts[0]), self::VALID_DIRECTIONS, true)) {
                throw new InvalidOperationValueException(
                    'order',
                    $value,
                    "Value must be 'asc' or 'desc', or 'column|direction'.",
                );
            }
        } elseif (count($parts) === 2) {
            // Explicit column: order:created_at|desc
            [$column, $direction] = $parts;

            if ($column === '' || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
                throw new InvalidOperationValueException(
                    'order',
                    $value,
                    "Column name must be a valid identifier.",
                );
            }

            if (!in_array(strtolower($direction), self::VALID_DIRECTIONS, true)) {
                throw new InvalidOperationValueException(
                    'order',
                    $value,
                    "Direction must be 'asc' or 'desc'.",
                );
            }
        } else {
            throw new InvalidOperationValueException(
                'order',
                $value,
                "Expected 'direction' or 'column|direction'.",
            );
        }
    }

    public function apply(QueryBuilder $qb, string $value): void
    {
        $parts = explode('|', $value);

        if (count($parts) === 1) {
            // Default to primary key (id)
            $qb->orderBy('id', strtoupper($parts[0]));
        } else {
            $qb->orderBy($parts[0], strtoupper($parts[1]));
        }
    }
}
