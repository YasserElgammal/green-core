<?php

namespace YasserElgammal\Green\Tests\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use YasserElgammal\Green\Exceptions\TokenMismatchException;
use YasserElgammal\Green\Http\Middleware\CsrfMiddleware;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Security\Csrf\CsrfConfig;
use YasserElgammal\Green\Security\Csrf\CsrfTokenManager;
use YasserElgammal\Green\Session\SessionManager;

class CsrfMiddlewareTest extends TestCase
{
    private SessionManager $session;
    private CsrfConfig $config;
    private CsrfTokenManager $tokenManager;

    /** A simple $next callable that always returns 200 OK. */
    private \Closure $next;

    protected function setUp(): void
    {
        $symfonySession     = new Session(new MockArraySessionStorage());
        $this->session      = new SessionManager($symfonySession);
        $this->config       = new CsrfConfig();
        $this->tokenManager = new CsrfTokenManager($this->session, $this->config);

        // Clean slate
        $this->session->forget($this->config->getSessionKey());

        $this->next = fn(Request $req) => new Response('OK', 200);
    }

    private function makeMiddleware(?CsrfConfig $config = null): CsrfMiddleware
    {
        $cfg = $config ?? $this->config;
        $tm  = new CsrfTokenManager($this->session, $cfg);
        return new CsrfMiddleware($cfg, $tm);
    }

    // ─── Safe methods ────────────────────────────────────────

    public function testGetRequestPassesThrough(): void
    {
        $request    = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
        $middleware = $this->makeMiddleware();

        $response = $middleware->handle($request, $this->next);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHeadRequestPassesThrough(): void
    {
        $request    = new Request([], [], ['REQUEST_METHOD' => 'HEAD', 'REQUEST_URI' => '/']);
        $middleware = $this->makeMiddleware();

        $response = $middleware->handle($request, $this->next);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testOptionsRequestPassesThrough(): void
    {
        $request    = new Request([], [], ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/']);
        $middleware = $this->makeMiddleware();

        $response = $middleware->handle($request, $this->next);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ─── Disabled ────────────────────────────────────────────

    public function testDisabledConfigSkipsValidation(): void
    {
        $config     = new CsrfConfig(['enabled' => false]);
        $request    = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/']);
        $middleware = $this->makeMiddleware($config);

        $response = $middleware->handle($request, $this->next);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ─── Excepted paths ──────────────────────────────────────

    public function testExceptedPathSkipsValidation(): void
    {
        $config     = new CsrfConfig(['except' => ['/webhooks']]);
        $request    = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/webhooks']);
        $middleware = $this->makeMiddleware($config);

        $response = $middleware->handle($request, $this->next);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testExceptedWildcardPathSkipsValidation(): void
    {
        $config     = new CsrfConfig(['except' => ['/api/*']]);
        $request    = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/users']);
        $middleware = $this->makeMiddleware($config);

        $response = $middleware->handle($request, $this->next);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ─── Valid token from body ───────────────────────────────

    public function testPostWithValidBodyTokenSucceeds(): void
    {
        $pair       = $this->tokenManager->generate();
        $request    = new Request([], [
            '_csrf_id'    => $pair['id'],
            '_csrf_token' => $pair['token'],
        ], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/posts']);
        $middleware = $this->makeMiddleware();

        $response = $middleware->handle($request, $this->next);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ─── Valid token from headers ────────────────────────────

    public function testPostWithValidHeaderTokenSucceeds(): void
    {
        $pair       = $this->tokenManager->generate();
        $request    = new Request([], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/posts',
            'HTTP_X_CSRF_ID'    => $pair['id'],
            'HTTP_X_CSRF_TOKEN' => $pair['token'],
        ]);
        $middleware = $this->makeMiddleware();

        $response = $middleware->handle($request, $this->next);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ─── Invalid / missing tokens ────────────────────────────

    public function testPostWithoutTokenThrows(): void
    {
        $request    = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/posts']);
        $middleware = $this->makeMiddleware();

        $this->expectException(TokenMismatchException::class);
        $middleware->handle($request, $this->next);
    }

    public function testPostWithInvalidTokenThrows(): void
    {
        $this->tokenManager->generate(); // ensure at least one exists
        $request    = new Request([], [
            '_csrf_id'    => 'bad_id',
            '_csrf_token' => 'bad_token',
        ], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/posts']);
        $middleware = $this->makeMiddleware();

        $this->expectException(TokenMismatchException::class);
        $middleware->handle($request, $this->next);
    }

    // ─── Other unsafe methods ────────────────────────────────

    public function testPutWithoutTokenThrows(): void
    {
        $request    = new Request([], [], ['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/posts/1']);
        $middleware = $this->makeMiddleware();

        $this->expectException(TokenMismatchException::class);
        $middleware->handle($request, $this->next);
    }

    public function testDeleteWithoutTokenThrows(): void
    {
        $request    = new Request([], [], ['REQUEST_METHOD' => 'DELETE', 'REQUEST_URI' => '/posts/1']);
        $middleware = $this->makeMiddleware();

        $this->expectException(TokenMismatchException::class);
        $middleware->handle($request, $this->next);
    }

    public function testPatchWithoutTokenThrows(): void
    {
        $request    = new Request([], [], ['REQUEST_METHOD' => 'PATCH', 'REQUEST_URI' => '/posts/1']);
        $middleware = $this->makeMiddleware();

        $this->expectException(TokenMismatchException::class);
        $middleware->handle($request, $this->next);
    }

    // ─── TokenMismatchException details ──────────────────────

    public function testTokenMismatchExceptionHas419Status(): void
    {
        $exception = new TokenMismatchException();

        $this->assertSame(419, $exception->getStatusCode());
        $this->assertSame('CSRF token mismatch.', $exception->getMessage());
    }
}
