<?php

namespace YasserElgammal\Green\Database\Relations;

/**
 * Defines a belongs-to (inverse) relationship.
 *
 * The foreign key lives ON the current model pointing to the related model.
 *
 * Example: Post belongsTo User
 *   - foreign_key = 'user_id'  (column on posts table)
 *   - owner_key   = 'id'       (column on users table)
 *
 * Usage:
 *   'author'   => new BelongsTo(User::class),
 *   'reviewer' => new BelongsTo(User::class, foreignKey: 'reviewer_id'),
 */
class BelongsTo extends Relation
{
    public readonly string $foreignKey;

    /**
     * @param  class-string  $model       The related model class
     * @param  string|null   $foreignKey  Column on the current model (default: snake(Model).'_id')
     * @param  string        $ownerKey    Column on the related model (default: 'id')
     */
    public function __construct(
        public readonly string $model,
        ?string $foreignKey = null,
        public readonly string $ownerKey = 'id',
    ) {
        // belongsTo can resolve foreign_key immediately from the related model name
        $this->foreignKey = $foreignKey ?? self::modelToForeignKey($model);
    }

    public function toArray(): array
    {
        return [
            'type'        => 'belongsTo',
            'model'       => $this->model,
            'foreign_key' => $this->foreignKey,
            'owner_key'   => $this->ownerKey,
        ];
    }

    public function resolveDefaults(string $parentSnake): void
    {
        // belongsTo resolves everything immediately, no deferred defaults needed
    }
}
