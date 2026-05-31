<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Aggregations;

use Doctrine\DBAL\ArrayParameterType;
use YasserElgammal\Green\Database\Database;
use YasserElgammal\Green\Database\IncludeQuery\Resolver\ResolvedAggregation;
use YasserElgammal\Green\Database\Model;

/**
 * Executes aggregation queries and injects scalar results into parent models.
 *
 * Generates optimized batch SQL using GROUP BY and WHERE IN — one query per
 * unique (relation, constraint-set) combination. Multiple aggregation functions
 * on the same relation are combined into a single query.
 *
 * Example generated SQL:
 *   SELECT post_id AS __agg_group_key,
 *          COUNT(*) AS comments_count,
 *          AVG(rating) AS comments_avg_rating
 *   FROM comments
 *   WHERE post_id IN (1, 2, 3)
 *   GROUP BY post_id
 *
 * Supports all four relation types:
 *   - hasMany:    GROUP BY foreign_key
 *   - hasOne:     GROUP BY foreign_key
 *   - belongsTo:  GROUP BY owner_key
 *   - manyToMany: JOIN pivot, GROUP BY pivot.foreign_key
 *
 * Design Pattern: Strategy (delegates SQL expression generation to AggregationInterface)
 */
final class AggregationLoader
{
    /**
     * Load aggregation results for a batch of parent models.
     *
     * Combines multiple aggregations on the same relation into a single query
     * for optimal performance.
     *
     * @param  Model[]               $models       Hydrated parent model instances
     * @param  string                $relation     The relation key (e.g. 'comments')
     * @param  array                 $config       The relation config from the registry
     * @param  ResolvedAggregation[] $aggregations Aggregations to compute
     * @return Model[]               The same models with aggregation attributes injected
     */
    public function load(
        array $models,
        string $relation,
        array $config,
        array $aggregations,
    ): array {
        if (empty($models) || empty($aggregations)) {
            return $models;
        }

        $type = $config['type'] ?? throw new \InvalidArgumentException(
            "Relation [{$relation}] is missing the required [type] key."
        );

        return match ($type) {
            'hasMany', 'hasOne' => $this->loadHasRelation($models, $config, $aggregations),
            'belongsTo'         => $this->loadBelongsTo($models, $config, $aggregations),
            'manyToMany'        => $this->loadManyToMany($models, $config, $aggregations),
            default             => throw new \InvalidArgumentException(
                "Unsupported relation type [{$type}] for aggregation on [{$relation}]."
            ),
        };
    }

    /**
     * Load aggregations for hasMany / hasOne relations.
     *
     * SQL pattern:
     *   SELECT foreign_key AS __agg_group_key, COUNT(*), SUM(col), ...
     *   FROM related_table
     *   WHERE foreign_key IN (:ids)
     *   GROUP BY foreign_key
     */
    private function loadHasRelation(array $models, array $config, array $aggregations): array
    {
        $relatedBlueprint = new $config['model']();
        $foreignKey       = $config['foreign_key'];
        $localKey         = $config['local_key'];

        // Collect parent IDs
        $parentIds = $this->collectIds($models, $localKey);
        if (empty($parentIds)) {
            return $this->attachDefaults($models, $aggregations);
        }

        // Build and execute the aggregation query
        $connection = Database::getConnection();
        $qb         = $connection->createQueryBuilder();

        $qb->from($relatedBlueprint->getTable());

        // SELECT foreign_key AS __agg_group_key, ...aggregate expressions...
        $qb->select("{$foreignKey} AS __agg_group_key");

        foreach ($aggregations as $agg) {
            $qb->addSelect("{$agg->aggregation->sqlExpression($agg->column)} AS {$agg->attributeName}");
        }

        $qb->where("{$foreignKey} IN (:ids)")
           ->setParameter('ids', $parentIds, ArrayParameterType::INTEGER)
           ->groupBy($foreignKey);

        // Apply constraints if any aggregation has them
        foreach ($aggregations as $agg) {
            if ($agg->hasConstraint()) {
                ($agg->constraint)($qb);
            }
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();

        // Map results by parent ID and inject into models
        return $this->injectResults($models, $localKey, $rows, $aggregations);
    }

    /**
     * Load aggregations for belongsTo relations.
     *
     * For belongsTo, the foreign key lives on the PARENT model.
     * We aggregate on the related table grouped by the owner key.
     *
     * SQL pattern:
     *   SELECT owner_key AS __agg_group_key, COUNT(*), ...
     *   FROM related_table
     *   WHERE owner_key IN (:ids)
     *   GROUP BY owner_key
     */
    private function loadBelongsTo(array $models, array $config, array $aggregations): array
    {
        $relatedBlueprint = new $config['model']();
        $foreignKey       = $config['foreign_key']; // column on parent (post.user_id)
        $ownerKey         = $config['owner_key'];   // column on related (user.id)

        // Collect foreign key values from parent models
        $foreignIds = $this->collectIds($models, $foreignKey);
        if (empty($foreignIds)) {
            return $this->attachDefaults($models, $aggregations);
        }

        $connection = Database::getConnection();
        $qb         = $connection->createQueryBuilder();

        $qb->from($relatedBlueprint->getTable());
        $qb->select("{$ownerKey} AS __agg_group_key");

        foreach ($aggregations as $agg) {
            $qb->addSelect("{$agg->aggregation->sqlExpression($agg->column)} AS {$agg->attributeName}");
        }

        $qb->where("{$ownerKey} IN (:ids)")
           ->setParameter('ids', $foreignIds, ArrayParameterType::INTEGER)
           ->groupBy($ownerKey);

        foreach ($aggregations as $agg) {
            if ($agg->hasConstraint()) {
                ($agg->constraint)($qb);
            }
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();

        // For belongsTo, the join key on the parent side is the foreignKey
        return $this->injectResults($models, $foreignKey, $rows, $aggregations);
    }

    /**
     * Load aggregations for manyToMany relations.
     *
     * SQL pattern:
     *   SELECT pivot.foreign_key AS __agg_group_key, COUNT(*), ...
     *   FROM related_table
     *   INNER JOIN pivot ON pivot.related_key = related.pk
     *   WHERE pivot.foreign_key IN (:ids)
     *   GROUP BY pivot.foreign_key
     */
    private function loadManyToMany(array $models, array $config, array $aggregations): array
    {
        $relatedBlueprint = new $config['model']();
        $relatedTable     = $relatedBlueprint->getTable();
        $relatedPk        = $relatedBlueprint->getPrimaryKey();
        $pivotTable       = $config['pivot'];
        $foreignKey       = $config['foreign_key']; // pivot column: user_id
        $relatedKey       = $config['related_key']; // pivot column: role_id
        $localKey         = $config['local_key'];   // parent column: id

        // Collect parent IDs
        $parentIds = $this->collectIds($models, $localKey);
        if (empty($parentIds)) {
            return $this->attachDefaults($models, $aggregations);
        }

        $connection = Database::getConnection();
        $qb         = $connection->createQueryBuilder();

        $qb->from($relatedTable)
           ->innerJoin(
               $relatedTable,
               $pivotTable,
               $pivotTable,
               "{$pivotTable}.{$relatedKey} = {$relatedTable}.{$relatedPk}"
           );

        $qb->select("{$pivotTable}.{$foreignKey} AS __agg_group_key");

        foreach ($aggregations as $agg) {
            $qb->addSelect("{$agg->aggregation->sqlExpression($agg->column)} AS {$agg->attributeName}");
        }

        $qb->where("{$pivotTable}.{$foreignKey} IN (:ids)")
           ->setParameter('ids', $parentIds, ArrayParameterType::INTEGER)
           ->groupBy("{$pivotTable}.{$foreignKey}");

        foreach ($aggregations as $agg) {
            if ($agg->hasConstraint()) {
                ($agg->constraint)($qb);
            }
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();

        return $this->injectResults($models, $localKey, $rows, $aggregations);
    }

    // ─── Internal helpers ────────────────────────────────────────────────────

    /**
     * Collect unique non-null values of a given key from models.
     *
     * @param  Model[]  $models
     * @param  string   $key
     * @return array
     */
    private function collectIds(array $models, string $key): array
    {
        return array_values(
            array_unique(
                array_filter(
                    array_map(fn(Model $m) => $m->get($key), $models),
                    fn($id) => $id !== null,
                )
            )
        );
    }

    /**
     * Inject aggregation results into parent models.
     *
     * Maps the GROUP BY results by the join key, then for each parent model
     * looks up its aggregation row and injects the cast values as attributes.
     * Models without matching rows get default values.
     *
     * @param  Model[]               $models
     * @param  string                $joinKey       The attribute name on the parent to match
     * @param  array                 $rows          Raw DB result rows
     * @param  ResolvedAggregation[] $aggregations
     * @return Model[]
     */
    private function injectResults(
        array $models,
        string $joinKey,
        array $rows,
        array $aggregations,
    ): array {
        // Index rows by the group key
        $indexed = [];
        foreach ($rows as $row) {
            $groupKey = $row['__agg_group_key'];
            $indexed[$groupKey] = $row;
        }

        // Inject into each model
        foreach ($models as $model) {
            $id  = $model->get($joinKey);
            $row = $indexed[$id] ?? null;

            foreach ($aggregations as $agg) {
                $attrName = $agg->attributeName;
                $rawValue = $row[$attrName] ?? null;

                $model->set($attrName, $agg->aggregation->castResult($rawValue));
            }
        }

        return $models;
    }

    /**
     * Attach default values for all aggregations when no parent IDs exist.
     *
     * @param  Model[]               $models
     * @param  ResolvedAggregation[] $aggregations
     * @return Model[]
     */
    private function attachDefaults(array $models, array $aggregations): array
    {
        foreach ($models as $model) {
            foreach ($aggregations as $agg) {
                $model->set(
                    $agg->attributeName,
                    $agg->aggregation->castResult(null),
                );
            }
        }

        return $models;
    }
}
