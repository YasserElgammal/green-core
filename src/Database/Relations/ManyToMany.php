<?php

namespace YasserElgammal\Green\Database\Relations;

/**
 * Defines a many-to-many relationship through a pivot table.
 *
 * Example: User manyToMany Role through 'user_roles'
 *   - pivot       = 'user_roles'  (the junction table)
 *   - foreign_key = 'user_id'     (pivot column → parent)
 *   - related_key = 'role_id'     (pivot column → related)
 *   - local_key   = 'id'          (column on users table)
 *
 * Usage:
 *   'roles'       => new ManyToMany(Role::class, pivot: 'user_roles'),
 *   'permissions' => new ManyToMany(Permission::class, pivot: 'role_permissions', foreignKey: 'role_id'),
 */
class ManyToMany extends Relation
{
    public readonly string $relatedKey;

    /**
     * @param  class-string  $model       The related model class
     * @param  string        $pivot       The pivot/junction table name
     * @param  string|null   $foreignKey  Pivot column pointing to the current model (default: snake(ParentModel).'_id')
     * @param  string|null   $relatedKey  Pivot column pointing to the related model (default: snake(RelatedModel).'_id')
     * @param  string        $localKey    Column on the current model (default: 'id')
     */
    public function __construct(
        public readonly string $model,
        public readonly string $pivot,
        public ?string $foreignKey = null,
        ?string $relatedKey = null,
        public readonly string $localKey = 'id',
    ) {
        // related_key can be resolved immediately from the related model name
        $this->relatedKey = $relatedKey ?? self::modelToForeignKey($model);
    }

    public function toArray(): array
    {
        return [
            'type'        => 'manyToMany',
            'model'       => $this->model,
            'pivot'       => $this->pivot,
            'foreign_key' => $this->foreignKey,
            'related_key' => $this->relatedKey,
            'local_key'   => $this->localKey,
        ];
    }

    public function resolveDefaults(string $parentSnake): void
    {
        $this->foreignKey ??= "{$parentSnake}_id";
    }
}
