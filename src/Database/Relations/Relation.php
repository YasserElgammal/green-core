<?php

namespace YasserElgammal\Green\Database\Relations;

/**
 * Base class for all relation type definitions.
 *
 * Relation types are lightweight DTOs that describe how two models
 * are connected. They are designed to be used with PHP 8.1's
 * "new in initializers" feature, allowing clean property declarations:
 *
 *   protected array $relations = [
 *       'author' => new BelongsTo(User::class),
 *   ];
 *
 * Each subclass resolves smart defaults for foreign keys and local keys
 * based on model class naming conventions. Override any key when the
 * convention doesn't match your schema.
 *
 * @internal Subclassed by BelongsTo, HasMany, HasOne, ManyToMany
 */
abstract class Relation
{
    /**
     * Convert this relation definition to the config array
     * consumed by Table and RelationLoader.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * Resolve deferred defaults (like foreign keys that depend on the parent model).
     *
     * @param string $parentSnake The snake_case name of the parent model
     */
    abstract public function resolveDefaults(string $parentSnake): void;

    // ─── Shared Utilities ─────────────────────────────────────────────────

    /**
     * Convert a model FQCN to a foreign key name.
     *
     * Examples:
     *   'App\Models\User'       → 'user_id'
     *   'App\Models\BlogPost'   → 'blog_post_id'
     *
     * @param  string  $fqcn  Fully qualified class name
     */
    public static function modelToForeignKey(string $fqcn): string
    {
        return self::classToSnake($fqcn) . '_id';
    }

    /**
     * Convert a FQCN to snake_case base name.
     *
     * 'App\Models\BlogPost' → 'blog_post'
     */
    public static function classToSnake(string $fqcn): string
    {
        $baseName = substr(strrchr($fqcn, '\\'), 1) ?: $fqcn;

        return strtolower(
            preg_replace('/[A-Z]/', '_$0', lcfirst($baseName))
        );
    }
}
