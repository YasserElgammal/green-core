<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Operations;

use Doctrine\DBAL\Query\QueryBuilder;
use YasserElgammal\Green\Database\IncludeQuery\Exceptions\InvalidOperationValueException;

/**
 * Filters a relation's results by a column value.
 *
 * Syntax: filter:column=value
 * Effect: WHERE column = 'value'
 *
 * Multiple filters can be applied by repeating the operation
 * or using pipe-separated pairs: filter:status=active|type=public
 */
final class FilterOperation implements OperationInterface
{
    public function name(): string
    {
        return 'filter';
    }

    public function validate(string $value): void
    {
        if (!str_contains($value, '=')) {
            throw new InvalidOperationValueException(
                'filter',
                $value,
                "Expected format 'column=value'.",
            );
        }

        $parts = explode('=', $value, 2);

        if ($parts[0] === '' || $parts[1] === '') {
            throw new InvalidOperationValueException(
                'filter',
                $value,
                'Both column and value must be non-empty.',
            );
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $parts[0])) {
            throw new InvalidOperationValueException(
                'filter',
                $value,
                "Invalid column name '{$parts[0]}'.",
            );
        }
    }

    public function apply(QueryBuilder $qb, string $value): void
    {
        [$column, $filterValue] = explode('=', $value, 2);

        $paramName = 'filter_' . $column;
        $qb->andWhere("{$column} = :{$paramName}")
           ->setParameter($paramName, $filterValue);
    }
}
