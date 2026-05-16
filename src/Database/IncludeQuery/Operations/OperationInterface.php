<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Operations;

use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Contract for all Include Query operations.
 *
 * Each operation is a self-contained strategy that can:
 *   1. Identify itself by name
 *   2. Validate its incoming value
 *   3. Apply itself as a constraint to a QueryBuilder
 *
 * Design Pattern: Strategy
 */
interface OperationInterface
{
    /**
     * The canonical name used in include syntax (e.g. 'limit', 'order', 'select').
     */
    public function name(): string;

    /**
     * Validate the raw string value.
     *
     * @param  string  $value  The raw value from the parsed operation
     *
     * @throws \YasserElgammal\Green\Database\IncludeQuery\Exceptions\InvalidOperationValueException
     */
    public function validate(string $value): void;

    /**
     * Apply this operation as a constraint to the query builder.
     *
     * @param  QueryBuilder  $qb     The query builder to modify
     * @param  string        $value  The validated raw value
     */
    public function apply(QueryBuilder $qb, string $value): void;
}
