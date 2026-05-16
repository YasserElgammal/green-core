<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Operations;

use Doctrine\DBAL\Query\QueryBuilder;
use YasserElgammal\Green\Database\IncludeQuery\Exceptions\InvalidOperationValueException;

/**
 * Limits the number of rows returned for a relation.
 *
 * Syntax: limit:N  (where N is a positive integer)
 * Effect: setMaxResults(N)
 */
final class LimitOperation implements OperationInterface
{
    public function name(): string
    {
        return 'limit';
    }

    public function validate(string $value): void
    {
        if (!ctype_digit($value) || (int) $value <= 0) {
            throw new InvalidOperationValueException(
                'limit',
                $value,
                'Value must be a positive integer.',
            );
        }
    }

    public function apply(QueryBuilder $qb, string $value): void
    {
        $qb->setMaxResults((int) $value);
    }
}
