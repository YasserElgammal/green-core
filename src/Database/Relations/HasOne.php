<?php

namespace YasserElgammal\Green\Database\Relations;

/**
 * Defines a one-to-one relationship.
 *
 * Like hasMany but limits to a single related model per parent.
 * The foreign key lives ON the related model.
 *
 * Example: User hasOne Profile
 *   - foreign_key = 'user_id'  (column on profiles table)
 *   - local_key   = 'id'       (column on users table)
 *
 * Usage:
 *   'profile' => new HasOne(Profile::class),
 *   'avatar'  => new HasOne(Avatar::class, foreignKey: 'owner_id'),
 */
class HasOne extends Relation
{
    /**
     * @param  class-string  $model       The related model class
     * @param  string|null   $foreignKey  Column on the related model (default: snake(ParentModel).'_id')
     * @param  string        $localKey    Column on the current model (default: 'id')
     */
    public function __construct(
        public readonly string $model,
        public ?string $foreignKey = null,
        public readonly string $localKey = 'id',
    ) {}

    public function toArray(): array
    {
        return [
            'type'        => 'hasOne',
            'model'       => $this->model,
            'foreign_key' => $this->foreignKey,
            'local_key'   => $this->localKey,
        ];
    }

    public function resolveDefaults(string $parentSnake): void
    {
        $this->foreignKey ??= "{$parentSnake}_id";
    }
}
