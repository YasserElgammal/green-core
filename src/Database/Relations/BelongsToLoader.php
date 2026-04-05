<?php

namespace YasserElgammal\Green\Database\Relations;

use Doctrine\DBAL\ArrayParameterType;
use YasserElgammal\Green\Database\Database;
use YasserElgammal\Green\Database\Model;

/**
 * Loads a belongs-to (inverse of hasMany) relationship.
 *
 * For each parent model, the foreign_key lives ON the parent.
 * We collect all foreign_key values and fetch related rows in one query.
 *
 * Example: Post belongsTo User
 *   - foreign_key = 'user_id'  (column on post)
 *   - owner_key   = 'id'       (column on user)
 *
 * Single query, no N+1.
 */
class BelongsToLoader implements RelationLoader
{
    public function load(array $models, string $relation, array $config): array
    {
        // ── Validate config ──────────────────────────────────────────────────
        $this->validateConfig($relation, $config, ['model', 'foreign_key', 'owner_key']);

        /** @var Model $relatedBlueprint */
        $relatedBlueprint = new $config['model']();
        $foreignKey       = $config['foreign_key']; // column on the parent (post.user_id)
        $ownerKey         = $config['owner_key'];   // column on the related (user.id)

        // ── Collect all foreign key values from parents ──────────────────────
        $foreignIds = array_values(
            array_unique(
                array_filter(
                    array_map(fn(Model $m) => $m->get($foreignKey), $models),
                    fn($id) => $id !== null
                )
            )
        );

        if (empty($foreignIds)) {
            return $this->attachNull($models, $relation);
        }

        // ── Single query ─────────────────────────────────────────────────────
        $connection = Database::getConnection();
        $qb         = $connection->createQueryBuilder();

        $rows = $qb
            ->select('*')
            ->from($relatedBlueprint->getTable())
            ->where("{$ownerKey} IN (:ids)")
            ->setParameter('ids', $foreignIds, ArrayParameterType::INTEGER)
            ->executeQuery()
            ->fetchAllAssociative();

        // ── Index related models by their owner key ──────────────────────────
        $indexed = [];
        foreach ($rows as $row) {
            $relatedModel                  = (clone $relatedBlueprint)->fill($row);
            $indexed[$row[$ownerKey]]      = $relatedModel;
        }

        // ── Attach the matching parent model (single object, not array) ──────
        foreach ($models as $model) {
            $fkValue         = $model->get($foreignKey);
            $model->$relation = $indexed[$fkValue] ?? null;
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
