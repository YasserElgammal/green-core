<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Resolver;

/**
 * Value object representing a fully resolved include with its constraint closure.
 *
 * Produced by the IncludeResolver from a validated IncludeNode AST.
 * Consumed by the Table's loadIncludes() method.
 *
 * May also carry aggregation requests when the IQL expression contains
 * both data operations and aggregation operations (e.g., 'comments(limit:5,count)').
 */
final readonly class ResolvedInclude
{
    /**
     * @param  string                $relation      Relation name (e.g. 'comments')
     * @param  \Closure|null         $constraint    Closure to apply to QueryBuilder, or null for no constraints
     * @param  ResolvedInclude[]     $children      Nested resolved includes
     * @param  ResolvedAggregation[] $aggregations  Aggregation computations for this relation
     */
    public function __construct(
        public string    $relation,
        public ?\Closure $constraint = null,
        public array     $children = [],
        public array     $aggregations = [],
    ) {
    }

    /**
     * Whether this resolved include has query constraints.
     */
    public function hasConstraint(): bool
    {
        return $this->constraint !== null;
    }

    /**
     * Whether this resolved include has nested children.
     */
    public function hasChildren(): bool
    {
        return !empty($this->children);
    }

    /**
     * Whether this resolved include has aggregation computations.
     */
    public function hasAggregations(): bool
    {
        return !empty($this->aggregations);
    }

    /**
     * Whether this resolved include only has aggregations (no data load needed).
     *
     * When true, the relation loader is skipped — only the aggregation loader runs.
     */
    public function isAggregationOnly(): bool
    {
        return $this->hasAggregations() && !$this->hasConstraint() && !$this->hasChildren();
    }

    /**
     * Collect nested child relation names as dot-notation strings.
     *
     * @return string[]
     */
    public function childRelationNames(): array
    {
        return array_map(fn(ResolvedInclude $child) => $child->relation, $this->children);
    }
}
