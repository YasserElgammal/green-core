<?php

namespace YasserElgammal\Green\Tests\Routing;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Middleware\MiddlewareInterface;
use YasserElgammal\Green\Routing\ControllerResolver;
use YasserElgammal\Green\Routing\MiddlewareResolver;
use YasserElgammal\Green\Routing\Route;
use YasserElgammal\Green\Routing\Router;

class RouterResolverTest extends TestCase
{
    public function testDefaultControllerInstantiationStillWorks(): void
    {
        $router = new Router();
        $router->registerRoutesFromController(SimpleController::class);

        $response = $router->dispatch(new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/simple',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('simple', $response->getContent());
    }

    public function testResolversAllowConstructorDependencies(): void
    {
        $controllerResolver = new ControllerResolver();
        $controllerResolver->bind(
            DependentController::class,
            fn() => new DependentController(new ResolverDependency('controller'))
        );

        $middlewareResolver = new MiddlewareResolver();
        $middlewareResolver->bind(
            DependentMiddleware::class,
            fn() => new DependentMiddleware(new ResolverDependency('middleware'))
        );

        $router = new Router($controllerResolver, $middlewareResolver);
        $router->registerRoutesFromController(DependentController::class);

        $response = $router->dispatch(new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/resolver/42',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('controller:42:42:middleware', $response->getContent());
    }

    public function testConcreteConstructorDependenciesAreAutoWired(): void
    {
        $router = new Router();
        $router->registerRoutesFromController(AutoWiredController::class);

        $response = $router->dispatch(new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/auto-wired/7',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('user-7:auto-middleware', $response->getContent());
    }
}

final class ResolverDependency
{
    public function __construct(public readonly string $value)
    {
    }
}

final class SimpleController
{
    #[Route('GET', '/simple')]
    public function show(): string
    {
        return 'simple';
    }
}

final class DependentController
{
    public function __construct(private readonly ResolverDependency $dependency)
    {
    }

    #[Route('GET', '/resolver/{id}', [DependentMiddleware::class])]
    public function show(Request $request, string $id): Response
    {
        return new Response($this->dependency->value . ':' . $id . ':' . $request->getAttribute('id'));
    }
}

final class DependentMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ResolverDependency $dependency)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        return $response->setContent($response->getContent() . ':' . $this->dependency->value);
    }
}

final class AutoWiredController
{
    public function __construct(private readonly AutoWiredUserService $users)
    {
    }

    #[Route('GET', '/auto-wired/{id}', [AutoWiredMiddleware::class])]
    public function show(string $id): Response
    {
        return new Response($this->users->findName($id));
    }
}

final class AutoWiredUserService
{
    public function __construct(private readonly AutoWiredUserRepository $users)
    {
    }

    public function findName(string $id): string
    {
        return $this->users->findName($id);
    }
}

final class AutoWiredUserRepository
{
    public function findName(string $id): string
    {
        return 'user-' . $id;
    }
}

final class AutoWiredMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AutoWiredMiddlewareService $service)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        return $response->setContent($response->getContent() . ':' . $this->service->name());
    }
}

final class AutoWiredMiddlewareService
{
    public function name(): string
    {
        return 'auto-middleware';
    }
}
