<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Exceptions;

/**
 * Thrown when an aggregation operation is used incorrectly.
 *
 * Examples:
 *   - Using 'count' with a column: include('comments(count:foo)')
 *   - Using 'sum' without a column: include('orders(sum)')
 *   - Using an aggregation on an invalid relation type
 */
class InvalidAggregationException extends IncludeQueryException
{
    public function __construct(
        private readonly string $aggregationName,
        private readonly string $relation,
        string $reason = '',
    ) {
        $hint = $reason !== '' ? " {$reason}" : '';

        parent::__construct(
            "Invalid aggregation [{$aggregationName}] on relation [{$relation}].{$hint}"
        );
    }

    public function getAggregationName(): string
    {
        return $this->aggregationName;
    }

    public function getRelation(): string
    {
        return $this->relation;
    }
}
