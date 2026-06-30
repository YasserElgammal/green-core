<?php

namespace YasserElgammal\Green\Tests\Routing;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Http\Middleware\ThrottleRequests;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Routing\RateLimit\ArrayRateLimitStore;
use YasserElgammal\Green\Routing\RateLimiter;
use YasserElgammal\Green\Routing\Route;
use YasserElgammal\Green\Routing\Router;

class RateLimiterTest extends TestCase
{
    private int $now = 1000;

    public function testRequestAllowedWhenUnderLimitAndHeadersAdded(): void
    {
        $middleware = new ThrottleRequests($this->limiter(), 2, 1);
        $response = $middleware->handle($this->request(), fn() => new Response('OK'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('2', $response->getHeader('X-RateLimit-Limit'));
        $this->assertSame('1', $response->getHeader('X-RateLimit-Remaining'));
    }

    public function testRequestBlockedWhenLimitExceeded(): void
    {
        $middleware = new ThrottleRequests($this->limiter(), 1, 1);
        $middleware->handle($this->request(), fn() => new Response('OK'));
        $response = $middleware->handle($this->request(), fn() => new Response('OK'));

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('0', $response->getHeader('X-RateLimit-Remaining'));
        $this->assertSame('60', $response->getHeader('Retry-After'));
    }

    public function testCounterResetsAfterDecay(): void
    {
        $limiter = $this->limiter();
        $key = $limiter->key($this->request());
        $limiter->hit($key, 1, 60);
        $this->now += 61;

        $this->assertFalse($limiter->tooManyAttempts($key, 1));
    }

    public function testAuthenticatedUserKeyDiffersFromGuestAndPrefixIsolates(): void
    {
        $limiter = $this->limiter();
        $guest = $this->request();
        $user = $this->request();
        $user->setAttribute('user_id', 5);

        $this->assertNotSame($limiter->key($guest), $limiter->key($user));
        $this->assertNotSame($limiter->key($guest, 'login'), $limiter->key($guest, 'api'));
    }

    public function testRouteMiddlewareWorksWithThrottleAlias(): void
    {
        $limiter = $this->limiter();
        $router = new Router();
        $router->aliasMiddleware('throttle', fn(string $max, string $decay) => new ThrottleRequests($limiter, (int) $max, (int) $decay));
        $router->registerRoutesFromController(RateLimitedController::class);

        $first = $router->dispatch($this->request('/limited'));
        $second = $router->dispatch($this->request('/limited'));

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(429, $second->getStatusCode());
    }

    private function limiter(): RateLimiter
    {
        return new RateLimiter(new ArrayRateLimitStore(), fn() => $this->now);
    }

    private function request(string $path = '/'): Request
    {
        return new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => $path,
            'REMOTE_ADDR' => '10.0.0.1',
        ]);
    }
}
final class RateLimitedController
{
    #[Route('GET', '/limited', middleware: ['throttle:1,1'])]
    public function index(): Response
    {
        return new Response('OK');
    }
}