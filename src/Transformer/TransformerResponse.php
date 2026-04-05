<?php

namespace YasserElgammal\Green\Transformer;

use YasserElgammal\Green\Database\Model;
use YasserElgammal\Green\Http\JsonResponse;

/**
 * TransformerResponse — produces the final structured JSON envelope.
 *
 * Combines a Transformer with optional pagination meta to return
 * clean, consistent API responses.
 *
 * Output format:
 *   Single item:   { "data": { ... } }
 *   Collection:    { "data": [ ... ] }
 *   Paginated:     { "data": [ ... ], "meta": { ... } }
 */
class TransformerResponse
{
    /**
     * Return a single transformed item.
     *
     * @param  Model        $model
     * @param  Transformer  $transformer
     * @param  int          $status  HTTP status code
     * @return JsonResponse
     */
    public static function item(Model $model, Transformer $transformer, int $status = 200): JsonResponse
    {
        return new JsonResponse([
            'data' => $transformer->process($model),
        ], $status);
    }

    /**
     * Return a transformed collection (no pagination meta).
     *
     * @param  Model[]      $models
     * @param  Transformer  $transformer
     * @param  int          $status  HTTP status code
     * @return JsonResponse
     */
    public static function collection(array $models, Transformer $transformer, int $status = 200): JsonResponse
    {
        return new JsonResponse([
            'data' => $transformer->collection($models),
        ], $status);
    }

    /**
     * Return a transformed, paginated response.
     *
     * Expects the result from Table::paginate():
     *   ['data' => Model[], 'meta' => [...]]
     *
     * @param  array        $paginatorResult  Output from Table::paginate()
     * @param  Transformer  $transformer
     * @param  int          $status  HTTP status code
     * @return JsonResponse
     */
    public static function paginated(array $paginatorResult, Transformer $transformer, int $status = 200): JsonResponse
    {
        $models = $paginatorResult['data'] ?? [];
        $meta   = $paginatorResult['meta'] ?? [];

        return new JsonResponse([
            'data' => $transformer->collection($models),
            'meta' => $meta,
        ], $status);
    }
}
