<?php

namespace YasserElgammal\Green\Database\Relations;

use Doctrine\DBAL\ArrayParameterType;
use YasserElgammal\Green\Database\Database;
use YasserElgammal\Green\Database\Model;

/**
 * Loads a one-to-one (hasOne) relationship.
 *
 * Like hasMany but limits to the FIRST matching related row per parent,
 * attaching a single Model (or null) instead of an array.
 *
 * Example: User hasOne Profile
 *   - foreign_key = 'user_id'  (column on profile)
 *   - local_key   = 'id'       (column on user)
 *
 * Single query, no N+1.
 */
class HasOneLoader implements RelationLoader
{
    public function load(array $models, string $relation, array $config): array
    {
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
            return $this->attachNull($models, $relation);
        }

        // ── Single WHERE IN query ────────────────────────────────────────────
        $connection = Database::getConnection();
        $qb         = $connection->createQueryBuilder();

        $rows = $qb
            ->select('*')
            ->from($relatedBlueprint->getTable())
            ->where("{$foreignKey} IN (:ids)")
            ->setParameter('ids', $parentIds, ArrayParameterType::INTEGER)
            ->executeQuery()
            ->fetchAllAssociative();

        // ── Index by foreign key — first match wins ──────────────────────────
        $indexed = [];
        foreach ($rows as $row) {
            $parentId = $row[$foreignKey];
            if (!isset($indexed[$parentId])) {
                $indexed[$parentId] = (clone $relatedBlueprint)->fill($row);
            }
        }

        // ── Attach single model (or null) to each parent ─────────────────────
        foreach ($models as $model) {
            $id              = $model->get($localKey);
            $model->$relation = $indexed[$id] ?? null;
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
