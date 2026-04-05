<?php

use YasserElgammal\Green\Database\Model;
use YasserElgammal\Green\Http\JsonResponse;
use YasserElgammal\Green\Pagination\Paginator;
use YasserElgammal\Green\Transformer\Transformer;
use YasserElgammal\Green\Transformer\TransformerResponse;
use YasserElgammal\Green\View\View;
use YasserElgammal\Green\Session\SessionManager;
use YasserElgammal\Green\Http\RedirectResponse;

if (!function_exists('response_json')) {
    function response_json(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status);
    }
}

if (!function_exists('paginate')) {
    function paginate(mixed $items, int $perPage, int $page): JsonResponse
    {
        $paginator = new Paginator();
        $result = $paginator->paginate($items, $perPage, $page);
        return new JsonResponse($result);
    }
}

if (!function_exists('transform')) {
    /**
     * Transform a model or collection through a Transformer.
     *
     * @param  Model|Model[]  $data
     * @param  Transformer    $transformer
     * @param  int            $status  HTTP status code
     * @return JsonResponse
     */
    function transform(Model|array $data, Transformer $transformer, int $status = 200): JsonResponse
    {
        if ($data instanceof Model) {
            return TransformerResponse::item($data, $transformer, $status);
        }

        return TransformerResponse::collection($data, $transformer, $status);
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): string
    {
        return View::render($template, $data);
    }
}

if (!function_exists('session')) {
    function session(): SessionManager
    {
        static $session = null;
        if ($session === null) {
            $session = new SessionManager();
        }
        return $session;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }
}
