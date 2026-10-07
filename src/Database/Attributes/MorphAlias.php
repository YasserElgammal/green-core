<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\Attributes;

use Attribute;

/**
 * Declare the morph type alias for a Model.
 *
 * The alias is the short string stored in the database's type
 * discriminator column (e.g. 'post', 'video', 'product').
 *
 * Usage:
 *   #[MorphAlias('post')]
 *   class Post extends Model { ... }
 *
 * The attribute is read by MorphMap during relation loading
 * to automatically map the alias to the model class,
 * removing the need for a separate MorphMap::register() call.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class MorphAlias
{
    /**
     * @param string $alias The morph type alias (e.g. 'post', 'video')
     */
    public function __construct(
        public string $alias,
    ) {
    }
}
