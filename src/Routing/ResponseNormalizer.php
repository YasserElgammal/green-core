<?php

namespace YasserElgammal\Green\Routing;

use YasserElgammal\Green\Http\JsonResponse;
use YasserElgammal\Green\Http\Response;

final class ResponseNormalizer
{
    public function normalize(mixed $response): Response
    {
        $normalized = null;

        if ($response instanceof Response) {
            $normalized = $response;
        } elseif (is_array($response)) {
            $normalized = new JsonResponse($response);
        } elseif (is_string($response) || is_numeric($response)) {
            $normalized = new Response((string) $response);
        } elseif ($response === null) {
            $normalized = new Response('', 200);
        }

        if (!$normalized instanceof Response) {
            throw new \RuntimeException(
                'Controller returned unsupported response type [' . get_debug_type($response) . '].'
            );
        }

        return $normalized;
    }
}