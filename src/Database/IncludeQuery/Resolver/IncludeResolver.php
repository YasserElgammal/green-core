<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Resolver;

use Doctrine\DBAL\Query\QueryBuilder;
use YasserElgammal\Green\Database\IncludeQuery\Aggregations\AggregationRegistry;
use YasserElgammal\Green\Database\IncludeQuery\Ast\IncludeNode;
use YasserElgammal\Green\Database\IncludeQuery\Ast\Operation;
use YasserElgammal\Green\Database\IncludeQuery\Operations\OperationRegistry;

/**
 * Converts validated IncludeNode ASTs into ResolvedInclude objects
 * with composable constraint closures and resolved aggregations.
 *
 * Each regular operation is resolved via the OperationRegistry and composed
 * into a single Closure(QueryBuilder): void per relation.
 *
 * Aggregation operations are resolved via the AggregationRegistry and
 * attached as ResolvedAggregation objects to the ResolvedInclude.
 *
 * The resolver also injects required foreign key columns into SELECT
 * operations to preserve join integrity.
 */
final class IncludeResolver
{
    /**
     * Resolve an array of IncludeNode ASTs into ResolvedInclude objects.
     *
     * @param  IncludeNode[]                        $nodes
     * @param  array<string, array<string, mixed>>  $relations  The Table's $relations registry
     * @return ResolvedInclude[]
     */
    public function resolve(array $nodes, array $relations): array
    {
        $resolved = [];

        foreach ($nodes as $node) {
            $resolved[] = $this->resolveNode($node, $relations);
        }

        return $this->mergeResolvedIncludes($resolved);
    }

    /**
     * Merge ResolvedInclude objects that target the same relation.
     * 
     * @param ResolvedInclude[] $includes
     * @return ResolvedInclude[]
     */
    private function mergeResolvedIncludes(array $includes): array
    {
        /** @var array<string, ResolvedInclude> $merged */
        $merged = [];

        foreach ($includes as $include) {
            $relation = $include->relation;
            
            if (!isset($merged[$relation])) {
                $merged[$relation] = $include;
            } else {
                $existing = $merged[$relation];
                
                // Keep the existing constraint if present (or take the new one)
                $constraint = $existing->constraint ?? $include->constraint;

                // Merge aggregations
                $aggregations = array_merge($existing->aggregations, $include->aggregations);
                
                // Merge children and recursively merge them
                $children = array_merge($existing->children, $include->children);
                $children = $this->mergeResolvedIncludes($children);

                $merged[$relation] = new ResolvedInclude(
                    relation:     $relation,
                    constraint:   $constraint,
                    children:     $children,
                    aggregations: $aggregations,
                );
            }
        }

        return array_values($merged);
    }

    /**
     * Resolve a single IncludeNode into a ResolvedInclude.
     *
     * Partitions operations into:
     *   - Constraint operations (limit, order, select, filter, offset) → closure
     *   - Aggregation operations (count, sum, avg, etc.) → ResolvedAggregation[]
     */
    private function resolveNode(IncludeNode $node, array $relations): ResolvedInclude
    {
        $constraint   = null;
        $aggregations = [];
        $config       = $relations[$node->relation] ?? [];

        if ($node->hasOperations()) {
            // Partition operations into constraints and aggregations
            $constraintOps  = $node->operations->getNonAggregations();
            $aggregationOps = $node->operations->getAggregations();

            // Build constraint closure from non-aggregation operations
            if (!empty($constraintOps)) {
                $constraint = $this->buildConstraint($constraintOps, $config);
            }

            // Resolve aggregation operations
            if (!empty($aggregationOps)) {
                $aggregations = $this->resolveAggregations($node->relation, $aggregationOps);
            }
        }

        // Resolve children recursively
        $children = [];
        if ($node->child !== null) {
            $children = [$this->resolveNode($node->child, [])];
        }

        return new ResolvedInclude(
            relation:     $node->relation,
            constraint:   $constraint,
            children:     $children,
            aggregations: $aggregations,
        );
    }

    /**
     * Build a single constraint closure from regular (non-aggregation) operations.
     *
     * The closure composes all individual operation effects into one
     * callable that modifies the QueryBuilder in sequence.
     *
     * @param  Operation[]  $operations
     * @param  array        $config
     * @return \Closure(QueryBuilder): void
     */
    private function buildConstraint(array $operations, array $config): \Closure
    {
        $relationConfig = $config;

        return function (QueryBuilder $qb) use ($operations, $relationConfig): void {
            $hasSelect = false;

            foreach ($operations as $operation) {
                $handler = OperationRegistry::resolve($operation->name);
                $handler->apply($qb, $operation->rawValue);

                if ($operation->name === 'select') {
                    $hasSelect = true;
                }
            }

            // If SELECT was used, ensure foreign keys are included for join integrity
            if ($hasSelect) {
                $this->ensureJoinColumns($qb, $relationConfig);
            }
        };
    }

    /**
     * Resolve aggregation operations into ResolvedAggregation objects.
     *
     * @param  string       $relation
     * @param  Operation[]  $operations
     * @return ResolvedAggregation[]
     */
    private function resolveAggregations(string $relation, array $operations): array
    {
        $aggregations = [];

        foreach ($operations as $operation) {
            $aggregation = AggregationRegistry::resolve($operation->name);
            $column      = $operation->rawValue;

            $aggregations[] = new ResolvedAggregation(
                relation:      $relation,
                aggregation:   $aggregation,
                column:        $column,
                attributeName: $aggregation->attributeName($relation, $column),
            );
        }

        return $aggregations;
    }

    /**
     * Ensure foreign key columns are present in the SELECT clause.
     *
     * When a user selects specific columns like `select:id|name`,
     * we must also include the foreign key column so the relation
     * loader can properly group/attach results.
     */
    private function ensureJoinColumns(QueryBuilder $qb, array $config): void
    {
        // Determine which join column(s) are needed based on relation type
        $joinColumns = [];

        if (isset($config['foreign_key'])) {
            $joinColumns[] = $config['foreign_key'];
        }

        if (isset($config['owner_key'])) {
            $joinColumns[] = $config['owner_key'];
        }

        if (isset($config['local_key'])) {
            $joinColumns[] = $config['local_key'];
        }

        if (empty($joinColumns)) {
            return;
        }

        // Read current SELECT parts and add missing join columns
        $selectPart = $qb->getQueryPart('select');

        if (is_array($selectPart)) {
            $existing = array_map('trim', $selectPart);

            // Don't modify if SELECT * is used
            if (in_array('*', $existing, true)) {
                return;
            }

            foreach ($joinColumns as $col) {
                if (!in_array($col, $existing, true)) {
                    $qb->addSelect($col);
                }
            }
        }
    }
}
