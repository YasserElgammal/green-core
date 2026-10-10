<?php

namespace YasserElgammal\Green\Database\Relations;

use Doctrine\DBAL\ArrayParameterType;
use YasserElgammal\Green\Database\Database;
use YasserElgammal\Green\Database\Model;

/**
 * Loads a polymorphic one-to-many (morphMany) relationship.
 *
 * SELECT * FROM related_table 
 * WHERE type_column = :type AND id_column IN (:parentIds)
 *
 * Single query, no N+1.
 */
class MorphManyLoader implements RelationLoader
{
    public function load(array $models, string $relation, array $config, ?\Closure $constraint = null): array
    {
        // ── Validate config ──────────────────────────────────────────────────
        $this->validateConfig($relation, $config, ['model', 'type_column', 'id_column', 'local_key']);

        if (empty($models)) {
            return [];
        }

        /** @var Model $relatedBlueprint */
        $relatedBlueprint = new $config['model']();
        $typeColumn       = $config['type_column'];
        $idColumn         = $config['id_column'];
        $localKey         = $config['local_key'];

        // Resolve the morph type for the parents.
        // If 'morph_type' is explicitly set in the config, use it.
        // Otherwise, resolve it from the MorphMap using the first parent's class.
        $morphType = $config['morph_type'] ?? null;
        if ($morphType === null) {
            $parentClass = get_class($models[0]);
            $morphType   = MorphMap::alias($parentClass);
        }

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

        // ── Single query using WHERE type = ? AND id IN (?) ──────────────────
        $connection = Database::getConnection();
        $qb         = $connection->createQueryBuilder();

        $qb
            ->select('*')
            ->from($relatedBlueprint->getTable())
            ->where("{$typeColumn} = :type")
            ->andWhere("{$idColumn} IN (:ids)")
            ->setParameter('type', $morphType)
            ->setParameter('ids', $parentIds, ArrayParameterType::INTEGER);

        // Apply IQL constraints (limit, order, select, filter, etc.)
        if ($constraint !== null) {
            $constraint($qb);
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();

        // ── Hydrate related rows into Model instances ────────────────────────
        $related = array_map(fn($row) => (clone $relatedBlueprint)->fill($row), $rows);

        // ── Group related models by parent ID ────────────────────────────────
        $grouped = [];
        foreach ($related as $relatedModel) {
            $parentId = $relatedModel->get($idColumn);
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
