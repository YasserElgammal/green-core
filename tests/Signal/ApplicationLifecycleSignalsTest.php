<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Signal;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use YasserElgammal\Green\Application;
use YasserElgammal\Green\Exceptions\ExceptionHandler;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Routing\Router;
use YasserElgammal\Green\Signal\LifecycleSignals;
use YasserElgammal\Green\Signal\SignalDispatcher;

final class ApplicationLifecycleSignalsTest extends TestCase
{
    public function test_it_emits_request_received_then_request_handled(): void
    {
        $request = new Request(server: ['REQUEST_URI' => '/signals']);
        $response = new Response('ok');
        $signals = new SignalDispatcher();
        $events = [];
        $signals->listen(LifecycleSignals::REQUEST_RECEIVED, function (array $payload) use (&$events, $request): void {
            self::assertSame($request, $payload['request']);
            $events[] = LifecycleSignals::REQUEST_RECEIVED;
        });
        $signals->listen(LifecycleSignals::REQUEST_HANDLED, function (array $payload) use (&$events, $request, $response): void {
            self::assertSame($request, $payload['request']);
            self::assertSame($response, $payload['response']);
            $events[] = LifecycleSignals::REQUEST_HANDLED;
        });

        $app = $this->applicationWithRouter(new class($response) extends Router {
            public function __construct(private readonly Response $response)
            {
            }

            public function dispatch(Request $request): Response
            {
                return $this->response;
            }
        }, $signals);

        self::assertSame($response, $app->handle($request));
        self::assertSame($request, $app->make(Request::class));
        self::assertSame([
            LifecycleSignals::REQUEST_RECEIVED,
            LifecycleSignals::REQUEST_HANDLED,
        ], $events);
    }

    public function test_it_emits_exception_and_handled_signals_for_a_rendered_failure(): void
    {
        $request = new Request(server: [
            'REQUEST_URI' => '/api/signals',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $signals = new SignalDispatcher();
        $events = [];
        $signals->listen(LifecycleSignals::EXCEPTION_OCCURRED, function (array $payload) use (&$events): void {
            self::assertInstanceOf(RuntimeException::class, $payload['exception']);
            $events[] = LifecycleSignals::EXCEPTION_OCCURRED;
        });
        $signals->listen(LifecycleSignals::REQUEST_HANDLED, function (array $payload) use (&$events): void {
            self::assertSame(500, $payload['response']->getStatusCode());
            $events[] = LifecycleSignals::REQUEST_HANDLED;
        });

        $app = $this->applicationWithRouter(new class extends Router {
            public function dispatch(Request $request): Response
            {
                throw new RuntimeException('request failed');
            }
        }, $signals);
        $app->instance(ExceptionHandler::class, new ExceptionHandler());

        self::assertSame(500, $app->handle($request)->getStatusCode());
        self::assertSame([
            LifecycleSignals::EXCEPTION_OCCURRED,
            LifecycleSignals::REQUEST_HANDLED,
        ], $events);
    }

    private function applicationWithRouter(Router $router, SignalDispatcher $signals): Application
    {
        $app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->router = $router;
        $app->instance(SignalDispatcher::class, $signals);

        return $app;
    }
}
