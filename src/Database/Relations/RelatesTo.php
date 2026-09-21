<?php

namespace YasserElgammal\Green\Database\Relations;

/**
 * Attribute used to define a relationship on a Table class.
 *
 * Example:
 *   #[RelatesTo('posts', new HasMany(Post::class))]
 *   #[RelatesTo('author', new BelongsTo(User::class))]
 *   class UserTable extends Table { ... }
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class RelatesTo
{
    public function __construct(
        public readonly string $name,
        public readonly Relation $relation,
    ) {}
}
