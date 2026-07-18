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
