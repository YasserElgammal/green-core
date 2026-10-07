<?php

namespace YasserElgammal\Green\Database\Relations;

use YasserElgammal\Green\Database\Attributes\MorphAlias;

/**
 * Explicit registry mapping morph type aliases to model classes.
 *
 * Polymorphic relations store a type discriminator in the database
 * (e.g. 'post', 'video'). This registry maps those short aliases
 * to their corresponding Model FQCN — and vice-versa.
 *
 * Three ways to populate the map (highest → lowest priority):
 *
 *   1. Explicit registration (override):
 *      MorphMap::register(['post' => Post::class]);
 *
 *   2. Attribute-based (recommended):
 *      #[MorphAlias('post')] on the Model class.
 *      Discovered automatically by MorphTo's `models` list
 *      or by MorphMany's reverse lookup.
 *
 *   3. Lazy attribute read:
 *      MorphMap::alias(Post::class) reads #[MorphAlias]
 *      from the class if not explicitly registered.
 *
 * Security: never instantiate a class from a raw database value.
 * Only aliases registered here (or discoverable via attribute) are accepted.
 */
class MorphMap
{
    /** @var array<string, class-string> alias → model class */
    private static array $map = [];

    /** @var array<class-string, string> model class → alias (reverse lookup) */
    private static array $reverse = [];

    /**
     * Register morph type aliases explicitly.
     *
     * This is the override / fallback method. Merges with
     * any previously registered mappings.
     *
     * @param array<string, class-string> $map alias => model FQCN
     */
    public static function register(array $map): void
    {
        foreach ($map as $alias => $modelClass) {
            self::$map[$alias]          = $modelClass;
            self::$reverse[$modelClass] = $alias;
        }
    }

    /**
     * Register one or more model classes by reading their #[MorphAlias] attributes.
     *
     * Usage:
     *   MorphMap::registerModels([Post::class, Video::class]);
     *
     * Each class must have #[MorphAlias('alias')] on it, otherwise it
     * is silently skipped (explicit register() can cover it instead).
     *
     * @param class-string[] $modelClasses
     */
    public static function registerModels(array $modelClasses): void
    {
        foreach ($modelClasses as $modelClass) {
            $alias = self::readAliasAttribute($modelClass);

            if ($alias !== null && !isset(self::$reverse[$modelClass])) {
                self::$map[$alias]          = $modelClass;
                self::$reverse[$modelClass] = $alias;
            }
        }
    }

    /**
     * Resolve a morph type alias to its model class.
     *
     * @throws \RuntimeException if the alias is not registered
     */
    public static function resolve(string $alias): string
    {
        if (isset(self::$map[$alias])) {
            return self::$map[$alias];
        }

        throw new \RuntimeException(
            "Unknown morph type [{$alias}]. " .
            "Register it via #[MorphAlias('{$alias}')] on the Model class, " .
            "or via MorphMap::register(). " .
            "Registered types: [" . implode(', ', array_keys(self::$map)) . "]."
        );
    }

    /**
     * Reverse-lookup: get the morph alias for a model class.
     *
     * Checks explicit registration first, then falls back to
     * reading the #[MorphAlias] attribute from the class.
     *
     * @throws \RuntimeException if no alias can be determined
     */
    public static function alias(string $modelClass): string
    {
        // 1. Explicit registration (highest priority)
        if (isset(self::$reverse[$modelClass])) {
            return self::$reverse[$modelClass];
        }

        // 2. Lazy attribute discovery
        $alias = self::readAliasAttribute($modelClass);

        if ($alias !== null) {
            // Cache for future lookups
            self::$map[$alias]          = $modelClass;
            self::$reverse[$modelClass] = $alias;
            return $alias;
        }

        throw new \RuntimeException(
            "No morph alias registered for [{$modelClass}]. " .
            "Add #[MorphAlias('alias')] to the class, or register via MorphMap::register()."
        );
    }

    /**
     * Check whether an alias is registered.
     */
    public static function has(string $alias): bool
    {
        return isset(self::$map[$alias]);
    }

    /**
     * Return all registered mappings.
     *
     * @return array<string, class-string>
     */
    public static function all(): array
    {
        return self::$map;
    }

    /**
     * Clear all registered mappings.
     *
     * Essential for test isolation — call in tearDown().
     */
    public static function reset(): void
    {
        self::$map     = [];
        self::$reverse = [];
    }

    /**
     * Read the #[MorphAlias] attribute from a model class.
     *
     * @return string|null The alias, or null if the attribute is absent.
     */
    private static function readAliasAttribute(string $modelClass): ?string
    {
        if (!class_exists($modelClass)) {
            return null;
        }

        $ref   = new \ReflectionClass($modelClass);
        $attrs = $ref->getAttributes(MorphAlias::class);

        if (empty($attrs)) {
            return null;
        }

        return $attrs[0]->newInstance()->alias;
    }
}
