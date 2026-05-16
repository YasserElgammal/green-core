<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Resolver;

use Doctrine\DBAL\Query\QueryBuilder;
use YasserElgammal\Green\Database\IncludeQuery\Ast\IncludeNode;
use YasserElgammal\Green\Database\IncludeQuery\Operations\OperationRegistry;

/**
 * Converts validated IncludeNode ASTs into ResolvedInclude objects
 * with composable constraint closures.
 *
 * Each operation is resolved via the OperationRegistry and composed
 * into a single Closure(QueryBuilder): void per relation.
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

        return $resolved;
    }

    /**
     * Resolve a single IncludeNode into a ResolvedInclude.
     */
    private function resolveNode(IncludeNode $node, array $relations): ResolvedInclude
    {
        $constraint = null;
        $config     = $relations[$node->relation] ?? [];

        if ($node->hasOperations()) {
            $constraint = $this->buildConstraint($node, $config);
        }

        // Resolve children recursively
        $children = [];
        if ($node->child !== null) {
            $children = [$this->resolveNode($node->child, [])];
        }

        return new ResolvedInclude(
            relation:   $node->relation,
            constraint: $constraint,
            children:   $children,
        );
    }

    /**
     * Build a single constraint closure from all operations on a node.
     *
     * The closure composes all individual operation effects into one
     * callable that modifies the QueryBuilder in sequence.
     *
     * @return \Closure(QueryBuilder): void
     */
    private function buildConstraint(IncludeNode $node, array $config): \Closure
    {
        $operations = $node->operations->all();
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
