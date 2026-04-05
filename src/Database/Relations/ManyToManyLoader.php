<?php

namespace YasserElgammal\Green\Database\Relations;

use Doctrine\DBAL\ArrayParameterType;
use YasserElgammal\Green\Database\Database;
use YasserElgammal\Green\Database\Model;

/**
 * Loads a many-to-many relationship through a pivot table.
 *
 * SELECT related.*, pivot.local_key_val
 * FROM related_table
 * INNER JOIN pivot_table ON pivot.related_key = related.pk
 * WHERE pivot.foreign_key IN (:parentIds)
 *
 * Single query, no N+1.
 */
class ManyToManyLoader implements RelationLoader
{
    public function load(array $models, string $relation, array $config): array
    {
        // ── Validate config ──────────────────────────────────────────────────
        $this->validateConfig($relation, $config, [
            'model', 'pivot', 'foreign_key', 'related_key', 'local_key',
        ]);

        /** @var Model $relatedBlueprint */
        $relatedBlueprint = new $config['model']();
        $relatedTable     = $relatedBlueprint->getTable();
        $relatedPk        = $relatedBlueprint->getPrimaryKey();
        $pivotTable       = $config['pivot'];
        $foreignKey       = $config['foreign_key']; // pivot column: user_id
        $relatedKey       = $config['related_key']; // pivot column: role_id
        $localKey         = $config['local_key'];   // parent column: id

        // ── Collect parent IDs ───────────────────────────────────────────────
        $parentIds = array_values(
            array_unique(
                array_filter(
                    array_map(fn(Model $m) => $m->get($localKey), $models),
                    fn($id) => $id !== null
                )
            )
        );

        if (empty($parentIds)) {
            return $this->attachEmpty($models, $relation);
        }

        // ── Single JOIN query through the pivot table ────────────────────────
        $connection = Database::getConnection();
        $qb         = $connection->createQueryBuilder();

        $rows = $qb
            ->select(
                "{$relatedTable}.*",
                "{$pivotTable}.{$foreignKey} AS __pivot_local_id"
            )
            ->from($relatedTable)
            ->innerJoin(
                $relatedTable,
                $pivotTable,
                $pivotTable,
                "{$pivotTable}.{$relatedKey} = {$relatedTable}.{$relatedPk}"
            )
            ->where("{$pivotTable}.{$foreignKey} IN (:ids)")
            ->setParameter('ids', $parentIds, ArrayParameterType::INTEGER)
            ->executeQuery()
            ->fetchAllAssociative();

        // ── Hydrate related rows into Model instances ────────────────────────
        $grouped = [];
        foreach ($rows as $row) {
            $pivotLocalId = $row['__pivot_local_id'];
            unset($row['__pivot_local_id']);

            $relatedModel = (clone $relatedBlueprint)->fill($row);
            $grouped[$pivotLocalId][] = $relatedModel;
        }

        // ── Attach grouped results to parent models ──────────────────────────
        foreach ($models as $model) {
            $id              = $model->get($localKey);
            $model->$relation = $grouped[$id] ?? [];
        }

        return $models;
    }

    private function attachEmpty(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->$relation = [];
        }
        return $models;
    }

    private function validateConfig(string $relation, array $config, array $required): void
    {
        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new \InvalidArgumentException(
                    "Relation [{$relation}] is missing required config key [{$key}]."
                );
            }
        }
    }
}
