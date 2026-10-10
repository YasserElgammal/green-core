<?php

namespace YasserElgammal\Green\Database\Relations;

/**
 * Defines a one-to-many polymorphic relationship (morphMany).
 *
 * The related model stores two columns that together point back
 * to the parent entity: a type discriminator and a foreign key.
 *
 * Example: Post morphMany Comment through 'commentable'
 *   - commentable_type = 'post'  (on comments table)
 *   - commentable_id   = 42      (on comments table, → posts.id)
 *
 * Column names default to {morphName}_type and {morphName}_id.
 *
 * Usage:
 *   'comments' => new MorphMany(Comment::class, 'commentable'),
 *
 * With explicit morph type override (skips MorphMap reverse lookup):
 *   'comments' => new MorphMany(Comment::class, 'commentable', morphType: 'blog_post'),
 *
 * With explicit column overrides:
 *   'comments' => new MorphMany(Comment::class, 'commentable',
 *       typeColumn: 'entity_type',
 *       idColumn:   'entity_id',
 *   ),
 */
class MorphMany extends Relation
{
    public readonly string $typeColumn;
    public readonly string $idColumn;

    /**
     * @param class-string $model       The related model class
     * @param string       $morphName   Polymorphic relation name (e.g. 'commentable')
     * @param string|null  $morphType   Override: type value in DB (default: from MorphMap)
     * @param string|null  $typeColumn  Column storing the morph type (default: {morphName}_type)
     * @param string|null  $idColumn    Column storing the foreign key (default: {morphName}_id)
     * @param string       $localKey    Column on the current model (default: 'id')
     */
    public function __construct(
        public readonly string  $model,
        public readonly string  $morphName,
        public readonly ?string $morphType = null,
        ?string                 $typeColumn = null,
        ?string                 $idColumn = null,
        public readonly string  $localKey = 'id',
    ) {
        $this->typeColumn = $typeColumn ?? "{$morphName}_type";
        $this->idColumn   = $idColumn ?? "{$morphName}_id";
    }

    public function toArray(): array
    {
        return [
            'type'        => 'morphMany',
            'model'       => $this->model,
            'morph_name'  => $this->morphName,
            'morph_type'  => $this->morphType,
            'type_column' => $this->typeColumn,
            'id_column'   => $this->idColumn,
            'local_key'   => $this->localKey,
        ];
    }

    public function resolveDefaults(string $parentSnake): void
    {
        // All defaults are resolved in the constructor from the morph name.
    }
}
