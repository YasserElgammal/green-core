<?php

namespace YasserElgammal\Green\Database\Relations;

/**
 * Defines a one-to-one polymorphic relationship (morphOne).
 *
 * The related model stores two columns that together point back
 * to the parent entity: a type discriminator and a foreign key.
 *
 * Example: User morphOne Image through 'imageable'
 *   - imageable_type = 'user'   (on images table)
 *   - imageable_id   = 42       (on images table, → users.id)
 *
 * Column names default to {morphName}_type and {morphName}_id.
 *
 * Usage:
 *   'image' => new MorphOne(Image::class, 'imageable'),
 */
class MorphOne extends Relation
{
    public readonly string $typeColumn;
    public readonly string $idColumn;

    /**
     * @param class-string $model       The related model class
     * @param string       $morphName   Polymorphic relation name (e.g. 'imageable')
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
            'type'        => 'morphOne',
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
