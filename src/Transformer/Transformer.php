<?php

namespace YasserElgammal\Green\Transformer;

use YasserElgammal\Green\Database\Model;

/**
 * Abstract Transformer — defines how a Model is serialized to JSON.
 *
 * Each model type gets its own concrete Transformer that:
 *   1. Declares which scalar fields to expose via transform().
 *   2. Maps relation names to child Transformers via includes().
 *   3. Automatically reads `_includes` from each model to know
 *      which relations were eager-loaded and need serializing.
 *
 * Design Pattern: Template Method + Composite (nested transformers)
 */
abstract class Transformer
{
    /**
     * Define the scalar fields to expose for this model.
     *
     * Return an associative array of key => value pairs.
     * Example:
     *   return [
     *       'id'   => $model->id,
     *       'name' => $model->name,
     *   ];
     *
     * @param  Model  $model
     * @return array<string, mixed>
     */
    abstract public function transform(Model $model): array;

    /**
     * Map relation names to their child Transformer instances.
     *
     * Override this in subclasses to declare available includes:
     *   return [
     *       'posts' => new PostTransformer(),
     *       'roles' => new RoleTransformer(),
     *   ];
     *
     * @return array<string, Transformer>
     */
    protected function includes(): array
    {
        return [];
    }

    /**
     * Transform a single model: scalar fields + loaded relations.
     *
     * Reads `_includes` from the model to decide which relations
     * to serialize. Only relations that were actually eager-loaded
     * AND have a matching child Transformer are included.
     *
     * @param  Model  $model
     * @return array<string, mixed>
     */
    public function process(Model $model): array
    {
        $output = $this->transform($model);

        // Read which relations were eager-loaded on this model
        $loadedIncludes   = $model->get('_includes', []);
        $childTransformers = $this->includes();

        foreach ($loadedIncludes as $relation) {
            // Only process if we have a child transformer for this relation
            if (!isset($childTransformers[$relation])) {
                continue;
            }

            $value       = $model->get($relation);
            $transformer = $childTransformers[$relation];

            if ($value === null) {
                $output[$relation] = null;
            } elseif ($value instanceof Model) {
                // belongsTo / hasOne → single object
                $output[$relation] = $transformer->process($value);
            } elseif (is_array($value)) {
                // hasMany / manyToMany → array of objects
                $output[$relation] = $transformer->collection($value);
            }
        }

        return $output;
    }

    /**
     * Transform an array of models.
     *
     * @param  Model[]  $models
     * @return array<int, array<string, mixed>>
     */
    public function collection(array $models): array
    {
        return array_map(fn(Model $model) => $this->process($model), $models);
    }
}
