<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Operations;

use Doctrine\DBAL\Query\QueryBuilder;
use YasserElgammal\Green\Database\IncludeQuery\Exceptions\InvalidOperationValueException;

/**
 * Controls which columns are selected for a relation.
 *
 * Syntax: select:col1|col2|col3
 * Effect: Replaces SELECT * with SELECT col1, col2, col3
 *
 * The pipe separator is used because commas separate operations.
 *
 * NOTE: The resolver layer will automatically inject required
 * foreign key columns to preserve join integrity.
 */
final class SelectOperation implements OperationInterface
{
    public function name(): string
    {
        return 'select';
    }

    public function validate(string $value): void
    {
        $columns = explode('|', $value);

        if (empty($columns) || ($columns === [''])) {
            throw new InvalidOperationValueException(
                'select',
                $value,
                'At least one column name is required.',
            );
        }

        foreach ($columns as $column) {
            $column = trim($column);

            if ($column === '') {
                throw new InvalidOperationValueException(
                    'select',
                    $value,
                    'Column names cannot be empty.',
                );
            }

            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
                throw new InvalidOperationValueException(
                    'select',
                    $value,
                    "Invalid column name '{$column}'. Use alphanumeric characters and underscores.",
                );
            }
        }
    }

    public function apply(QueryBuilder $qb, string $value): void
    {
        $columns = array_map('trim', explode('|', $value));

        // Reset any existing select and set specific columns
        $qb->select(...$columns);
    }
}
