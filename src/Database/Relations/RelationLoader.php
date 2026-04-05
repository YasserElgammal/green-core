<?php

namespace YasserElgammal\Green\Database\Relations;

use YasserElgammal\Green\Database\Model;

/**
 * Strategy interface for all relation loaders.
 *
 * Each concrete loader implements a single relation type
 * (hasMany, manyToMany, belongsTo, etc.) and is responsible
 * for fetching related rows and attaching them to parent models.
 *
 * Design Pattern: Strategy
 */
interface RelationLoader
{
    /**
     * Load related data and attach it to the given parent models.
     *
     * @param  Model[]  $models   Hydrated parent model instances
     * @param  string   $relation The relation key (e.g. 'posts')
     * @param  array    $config   The relation config from the registry
     * @return Model[]            The same models with relations attached
     */
    public function load(array $models, string $relation, array $config): array;
}
