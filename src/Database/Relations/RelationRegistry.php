<?php

namespace YasserElgammal\Green\Database\Relations;

/**
 * Maps relation type strings to their concrete loader implementations.
 *
 * Design Pattern: Strategy Registry
 *
 * Adding a new relation type requires only:
 *   1. Implementing RelationLoader
 *   2. Registering it here
 *
 * No changes to Table or any other class required.
 */
class RelationRegistry
{
    /** @var array<string, class-string<RelationLoader>> */
    private static array $loaders = [
        'hasOne'      => HasOneLoader::class,
        'hasMany'     => HasManyLoader::class,
        'manyToMany'  => ManyToManyLoader::class,
        'belongsTo'   => BelongsToLoader::class,
    ];

    /**
     * Resolve a loader instance for the given relation type.
     *
     * @throws \InvalidArgumentException if the type is not registered
     */
    public static function resolve(string $type): RelationLoader
    {
        if (!isset(self::$loaders[$type])) {
            throw new \InvalidArgumentException(
                "Unknown relation type [{$type}]. " .
                "Registered types: [" . implode(', ', array_keys(self::$loaders)) . "]."
            );
        }

        return new self::$loaders[$type]();
    }

    /**
     * Register a custom relation type at runtime.
     *
     * @param  string                        $type   e.g. 'morphMany'
     * @param  class-string<RelationLoader>  $loader Concrete RelationLoader class
     */
    public static function register(string $type, string $loader): void
    {
        if (!is_a($loader, RelationLoader::class, true)) {
            throw new \InvalidArgumentException(
                "Loader [{$loader}] must implement " . RelationLoader::class
            );
        }

        self::$loaders[$type] = $loader;
    }

    /**
     * List all currently registered relation types.
     *
     * @return string[]
     */
    public static function types(): array
    {
        return array_keys(self::$loaders);
    }
}
