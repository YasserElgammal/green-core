<?php

namespace YasserElgammal\Green\Database\Relations;

use Doctrine\DBAL\ArrayParameterType;
use YasserElgammal\Green\Database\Database;
use YasserElgammal\Green\Database\Model;

/**
 * Loads a polymorphic one-to-one (morphOne) relationship.
 *
 * SELECT * FROM related_table 
 * WHERE type_column = :type AND id_column IN (:parentIds)
 *
 * Attaches only the *first* related model it finds (or null).
 */
class MorphOneLoader implements RelationLoader
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
            return $this->attachNull($models, $relation);
        }

        // ── Single query ─────────────────────────────────────────────────────
        $connection = Database::getConnection();
        $qb         = $connection->createQueryBuilder();

        $qb
            ->select('*')
            ->from($relatedBlueprint->getTable())
            ->where("{$typeColumn} = :type")
            ->andWhere("{$idColumn} IN (:ids)")
            ->setParameter('type', $morphType)
            ->setParameter('ids', $parentIds, ArrayParameterType::INTEGER);

        if ($constraint !== null) {
            $constraint($qb);
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();

        // ── Hydrate related rows into Model instances ────────────────────────
        $related = array_map(fn($row) => (clone $relatedBlueprint)->fill($row), $rows);

        // ── Group related models by parent ID (keeping only the first) ───────
        $mapped = [];
        foreach ($related as $relatedModel) {
            $parentId = $relatedModel->get($idColumn);
            if (!isset($mapped[$parentId])) {
                $mapped[$parentId] = $relatedModel;
            }
        }

        // ── Attach mapped result to parent models ────────────────────────────
        foreach ($models as $model) {
            $id              = $model->get($localKey);
            $model->$relation = $mapped[$id] ?? null;
        }

        return $models;
    }

    private function attachNull(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->$relation = null;
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
