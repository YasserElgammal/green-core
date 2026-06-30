<?php

namespace YasserElgammal\Green\Http\Middleware;

use YasserElgammal\Green\Http\JsonResponse;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Middleware\MiddlewareInterface;
use YasserElgammal\Green\Routing\RateLimiter;

class ThrottleRequests implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly int $maxAttempts = 60,
        private readonly int $decayMinutes = 1,
        private readonly ?string $prefix = null,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $key = $this->limiter->key($request, $this->prefix);
        $state = $this->limiter->hit($key, $this->maxAttempts, $this->decayMinutes * 60);

        if ($state['exceeded']) {
            return $this->tooManyAttemptsResponse($state['retry_after']);
        }

        return $next($request)
            ->setHeader('X-RateLimit-Limit', (string) $this->maxAttempts)
            ->setHeader('X-RateLimit-Remaining', (string) $state['remaining']);
    }

    private function tooManyAttemptsResponse(int $retryAfter): JsonResponse
    {
        return (new JsonResponse([
            'success' => false,
            'message' => 'Too Many Attempts.',
            'errors' => [
                'rate_limit' => ['Too many requests. Please try again later.'],
            ],
        ], 429))
            ->setHeader('X-RateLimit-Limit', (string) $this->maxAttempts)
            ->setHeader('X-RateLimit-Remaining', '0')
            ->setHeader('Retry-After', (string) $retryAfter);
    }
}