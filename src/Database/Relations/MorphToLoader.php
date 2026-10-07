<?php

namespace YasserElgammal\Green\Database\Relations;

use Doctrine\DBAL\ArrayParameterType;
use YasserElgammal\Green\Database\Database;
use YasserElgammal\Green\Database\Model;

/**
 * Loads a morph-to relationship.
 *
 * For each parent model, the type and foreign key live ON the parent.
 * We collect all type+id pairs, group them by type, resolve the type
 * using the MorphMap, and run ONE query per unique type.
 *
 * Example: Comment morphTo (Post | Video)
 *   - type_column = 'commentable_type'
 *   - id_column   = 'commentable_id'
 *
 * Query execution:
 *   If comments point to Posts (10, 15) and Videos (7):
 *   -> SELECT * FROM posts WHERE id IN (10, 15)
 *   -> SELECT * FROM videos WHERE id IN (7)
 */
class MorphToLoader implements RelationLoader
{
    public function load(array $models, string $relation, array $config, ?\Closure $constraint = null): array
    {
        $this->validateConfig($relation, $config, ['type_column', 'id_column']);

        // ── Auto-register morph models from the relation definition ──────
        // Reads #[MorphAlias] from each class listed in `models`.
        if (!empty($config['models'])) {
            MorphMap::registerModels($config['models']);
        }

        $typeColumn = $config['type_column'];
        $idColumn   = $config['id_column'];

        // ── Group IDs by morph type ──────────────────────────────────────────
        // Example: ['post' => [10, 15], 'video' => [7]]
        $typeMap = [];

        foreach ($models as $model) {
            $type = $model->get($typeColumn);
            $id   = $model->get($idColumn);

            if ($type !== null && $id !== null) {
                $typeMap[$type][$id] = true;
            }
        }

        if (empty($typeMap)) {
            return $this->attachNull($models, $relation);
        }

        // ── Fetch related models per type ────────────────────────────────────
        // Example: ['post' => [10 => Post(10), 15 => Post(15)], 'video' => [7 => Video(7)]]
        $resultsByType = [];

        $connection = Database::getConnection();

        foreach ($typeMap as $typeAlias => $idsMap) {
            // MorphMap::resolve throws if the type is unknown/unregistered
            $modelClass = MorphMap::resolve($typeAlias);
            
            /** @var Model $relatedBlueprint */
            $relatedBlueprint = new $modelClass();
            $relatedPk        = $relatedBlueprint->getPrimaryKey();
            $ids              = array_keys($idsMap);

            $qb = $connection->createQueryBuilder()
                ->select('*')
                ->from($relatedBlueprint->getTable())
                ->where("{$relatedPk} IN (:ids)")
                ->setParameter('ids', $ids, ArrayParameterType::INTEGER);

            // Apply IQL constraints (select, filter, etc.)
            if ($constraint !== null) {
                $constraint($qb);
            }

            $rows = $qb->executeQuery()->fetchAllAssociative();

            // Index by the related model's primary key
            foreach ($rows as $row) {
                $relatedModel = (clone $relatedBlueprint)->fill($row);
                $resultsByType[$typeAlias][$row[$relatedPk]] = $relatedModel;
            }
        }

        // ── Attach the matching model to each parent ─────────────────────────
        foreach ($models as $model) {
            $type = $model->get($typeColumn);
            $id   = $model->get($idColumn);

            if ($type !== null && $id !== null) {
                $model->$relation = $resultsByType[$type][$id] ?? null;
            } else {
                $model->$relation = null;
            }
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
