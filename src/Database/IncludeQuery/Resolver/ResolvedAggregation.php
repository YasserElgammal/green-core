<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Resolver;

use YasserElgammal\Green\Database\IncludeQuery\Aggregations\AggregationInterface;

/**
 * Value object representing a fully resolved aggregation to be loaded.
 *
 * Produced by the IncludeResolver when aggregation operations are found in the AST.
 * Consumed by the AggregationLoader to generate and execute SQL.
 *
 * Parallel to ResolvedInclude but for aggregate computations (scalar results)
 * rather than relation data (Model instances).
 */
final readonly class ResolvedAggregation
{
    /**
     * @param  string                $relation       Relation name (e.g. 'comments')
     * @param  AggregationInterface  $aggregation    The aggregation strategy instance
     * @param  string                $column         Column to aggregate (empty for count/exists)
     * @param  string                $attributeName  Attribute name for the parent model (e.g. 'comments_count')
     * @param  \Closure|null         $constraint     Optional constraint closure (filter, etc.)
     */
    public function __construct(
        public string               $relation,
        public AggregationInterface $aggregation,
        public string               $column,
        public string               $attributeName,
        public ?\Closure            $constraint = null,
    ) {
    }

    /**
     * Whether this aggregation has additional query constraints.
     */
    public function hasConstraint(): bool
    {
        return $this->constraint !== null;
    }
}
