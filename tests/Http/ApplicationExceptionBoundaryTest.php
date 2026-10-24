<?php

namespace YasserElgammal\Green\Tests\Http;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use YasserElgammal\Green\Application;
use YasserElgammal\Green\Exceptions\ExceptionHandler;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Routing\Router;
use YasserElgammal\Green\Auth\Auth;

class ApplicationExceptionBoundaryTest extends TestCase
{
    public function testRouterThrowableIsRenderedByExceptionHandler(): void
    {
        $_ENV['APP_DEBUG'] = 'false';
        $app = $this->applicationWithFailingRouter();
        $app->instance(ExceptionHandler::class, new ExceptionHandler());

        $response = $app->handle($this->jsonRequest());

        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('router secret', $response->getContent());
        self::assertSame('application/json', $response->getHeader('Content-Type'));
    }

    public function testHandlerFailureReturnsIndependentEmergencyResponse(): void
    {
        $app = $this->applicationWithFailingRouter();
        $app->bind(ExceptionHandler::class, static function (): never {
            throw new RuntimeException('container failure');
        });

        $response = $app->handle($this->jsonRequest());

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Internal Server Error', $response->getContent());
        self::assertSame('text/plain; charset=UTF-8', $response->getHeader('Content-Type'));
    }

    public function testCurrentIdentityReferenceIsClearedAfterAnException(): void
    {
        $app = $this->applicationWithFailingRouter();
        $auth = new Auth();
        $app->instance(Auth::class, $auth);
        $app->instance(ExceptionHandler::class, new ExceptionHandler());

        $app->handle($this->jsonRequest());

        self::assertNull($auth->user());
    }

    public function testNestedDispatchRestoresOuterRequestAndIdentity(): void
    {
        $app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $auth = new Auth();
        $app->instance(Auth::class, $auth);
        $router = new class extends Router {
            public Application $app;
            public Auth $auth;
            public ?Request $outer = null;

            public function dispatch(Request $request): Response
            {
                if ($request->getPath() === '/inner') {
                    $this->auth->synchronizeUser((object) ['id' => 'inner'], self::class);
                    return new Response('inner');
                }

                $this->outer = $request;
                $outerIdentity = (object) ['id' => 'outer'];
                $this->auth->synchronizeUser($outerIdentity, self::class);
                $this->app->handle(new Request(server: ['REQUEST_URI' => '/inner']));
                TestCase::assertSame($outerIdentity, $this->auth->user());
                TestCase::assertSame($request, $this->app->make(Request::class));

                return new Response('outer');
            }
        };
        $router->app = $app;
        $router->auth = $auth;
        $app->router = $router;

        self::assertSame('outer', $app->handle(new Request(server: ['REQUEST_URI' => '/outer']))->getContent());
        self::assertNull($auth->user());
    }

    private function applicationWithFailingRouter(): Application
    {
        $app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->router = new class extends Router {
            public function dispatch(Request $request): Response
            {
                throw new RuntimeException('router secret');
            }
        };

        return $app;
    }

    private function jsonRequest(): Request
    {
        return new Request(server: [
            'REQUEST_URI' => '/api/failure',
            'HTTP_ACCEPT' => 'application/json',
        ]);
    }
}
