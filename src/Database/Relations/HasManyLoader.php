<?php

namespace YasserElgammal\Green\Database\Relations;

use Doctrine\DBAL\ArrayParameterType;
use YasserElgammal\Green\Database\Database;
use YasserElgammal\Green\Database\Model;

/**
 * Loads a one-to-many (hasMany) relationship.
 *
 * SELECT * FROM related_table WHERE foreign_key IN (:parentIds)
 *
 * Single query, no N+1.
 */
class HasManyLoader implements RelationLoader
{
    public function load(array $models, string $relation, array $config): array
    {
        // ── Validate config ──────────────────────────────────────────────────
        $this->validateConfig($relation, $config, ['model', 'foreign_key', 'local_key']);

        /** @var Model $relatedBlueprint */
        $relatedBlueprint = new $config['model']();
        $foreignKey       = $config['foreign_key'];
        $localKey         = $config['local_key'];

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

        // ── Single query using WHERE IN ──────────────────────────────────────
        $connection = Database::getConnection();
        $qb         = $connection->createQueryBuilder();

        $rows = $qb
            ->select('*')
            ->from($relatedBlueprint->getTable())
            ->where("{$foreignKey} IN (:ids)")
            ->setParameter('ids', $parentIds, ArrayParameterType::INTEGER)
            ->executeQuery()
            ->fetchAllAssociative();

        // ── Hydrate related rows into Model instances ────────────────────────
        $related = array_map(fn($row) => (clone $relatedBlueprint)->fill($row), $rows);

        // ── Group related models by foreign_key → parent local_key ───────────
        $grouped = [];
        foreach ($related as $relatedModel) {
            $parentId = $relatedModel->get($foreignKey);
            $grouped[$parentId][] = $relatedModel;
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
