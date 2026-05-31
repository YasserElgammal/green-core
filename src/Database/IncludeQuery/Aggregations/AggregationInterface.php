<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Aggregations;

/**
 * Contract for all relation aggregation operators.
 *
 * Each implementation encapsulates a single SQL aggregate function
 * (COUNT, SUM, AVG, etc.) and knows how to:
 *   1. Identify itself by name
 *   2. Declare whether it requires a column argument
 *   3. Validate its column value
 *   4. Generate the SQL aggregate expression
 *   5. Build the attribute name for the parent model
 *   6. Cast the raw DB result to the correct PHP type
 *
 * Design Pattern: Strategy
 */
interface AggregationInterface
{
    /**
     * The canonical name used in include syntax (e.g. 'count', 'sum', 'avg').
     */
    public function name(): string;

    /**
     * Whether this aggregation requires a column value.
     *
     * count/exists → false (operate on all rows)
     * sum/avg/min/max → true (need a specific column)
     */
    public function requiresColumn(): bool;

    /**
     * Validate the column value (if applicable).
     *
     * @param  string  $value  The raw column name from the parsed operation
     *
     * @throws \YasserElgammal\Green\Database\IncludeQuery\Exceptions\InvalidOperationValueException
     */
    public function validate(string $value): void;

    /**
     * Generate the SQL aggregate expression.
     *
     * @param  string  $column  The column to aggregate (empty for count/exists)
     * @return string           e.g. 'COUNT(*)', 'SUM(price)', 'AVG(rating)'
     */
    public function sqlExpression(string $column): string;

    /**
     * Build the attribute name for the parent model.
     *
     * @param  string  $relation  The relation name (e.g. 'comments')
     * @param  string  $column    The column name (empty for count/exists)
     * @return string             e.g. 'comments_count', 'orders_sum_total'
     */
    public function attributeName(string $relation, string $column): string;

    /**
     * Cast the raw database result to the correct PHP type.
     *
     * @param  mixed  $raw  The raw value from the database
     * @return mixed        The cast value (int, float, bool, etc.)
     */
    public function castResult(mixed $raw): mixed;
}
