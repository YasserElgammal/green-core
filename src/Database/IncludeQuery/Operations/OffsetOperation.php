<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Operations;

use Doctrine\DBAL\Query\QueryBuilder;
use YasserElgammal\Green\Database\IncludeQuery\Exceptions\InvalidOperationValueException;

/**
 * Skips a number of rows from the beginning of a relation's results.
 *
 * Syntax: offset:N  (where N is a non-negative integer)
 * Effect: setFirstResult(N)
 *
 * Typically used in combination with `limit` for pagination-like behavior.
 */
final class OffsetOperation implements OperationInterface
{
    public function name(): string
    {
        return 'offset';
    }

    public function validate(string $value): void
    {
        if (!ctype_digit($value)) {
            throw new InvalidOperationValueException(
                'offset',
                $value,
                'Value must be a non-negative integer.',
            );
        }
    }

    public function apply(QueryBuilder $qb, string $value): void
    {
        $qb->setFirstResult((int) $value);
    }
}
