<?php

namespace YasserElgammal\Green\Database\Relations;

/**
 * Defines a polymorphic belongs-to relationship (morphTo).
 *
 * The current model stores two columns that together identify
 * the related entity: a type discriminator and a foreign key.
 *
 * Example: Comment morphTo (Post | Video | Product)
 *   - commentable_type = 'post'   (morph type alias)
 *   - commentable_id   = 42       (PK of the related row)
 *
 * Column names default to {morphName}_type and {morphName}_id.
 *
 * Usage (attribute-based — recommended):
 *   'commentable' => new MorphTo('commentable', models: [Post::class, Video::class]),
 *
 *   Each model must have #[MorphAlias('alias')] on it.
 *   The loader reads the alias from the attribute automatically.
 *
 * With explicit column overrides:
 *   'commentable' => new MorphTo('commentable',
 *       models:     [Post::class, Video::class],
 *       typeColumn: 'entity_type',
 *       idColumn:   'entity_id',
 *   ),
 */
class MorphTo extends Relation
{
    public readonly string $typeColumn;
    public readonly string $idColumn;

    /**
     * @param string        $morphName   Polymorphic relation name (e.g. 'commentable')
     * @param class-string[] $models     Model classes this relation can resolve to
     * @param string|null   $typeColumn  Column storing the morph type (default: {morphName}_type)
     * @param string|null   $idColumn    Column storing the foreign key (default: {morphName}_id)
     */
    public function __construct(
        public readonly string $morphName,
        public readonly array  $models = [],
        ?string $typeColumn = null,
        ?string $idColumn = null,
    ) {
        $this->typeColumn = $typeColumn ?? "{$morphName}_type";
        $this->idColumn   = $idColumn ?? "{$morphName}_id";
    }

    public function toArray(): array
    {
        return [
            'type'        => 'morphTo',
            'models'      => $this->models,
            'type_column' => $this->typeColumn,
            'id_column'   => $this->idColumn,
        ];
    }

    public function resolveDefaults(string $parentSnake): void
    {
        // All defaults are resolved in the constructor from the morph name.
    }
}
