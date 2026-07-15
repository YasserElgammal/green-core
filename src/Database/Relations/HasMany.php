<?php

namespace YasserElgammal\Green\Database\Relations;

/**
 * Defines a one-to-many relationship.
 *
 * The foreign key lives ON the related model pointing back to the parent.
 *
 * Example: User hasMany Post
 *   - foreign_key = 'user_id'  (column on posts table)
 *   - local_key   = 'id'       (column on users table)
 *
 * Usage:
 *   'posts'   => new HasMany(Post::class),
 *   'replies' => new HasMany(Comment::class, foreignKey: 'parent_id'),
 */
class HasMany extends Relation
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
            'type'        => 'hasMany',
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
