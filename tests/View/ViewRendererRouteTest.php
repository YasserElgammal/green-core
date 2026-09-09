<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\View;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Twig\Error\RuntimeError;
use YasserElgammal\Green\Application;
use YasserElgammal\Green\ErrorHandling\GreenErrorKernel;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Routing\Route;
use YasserElgammal\Green\View\ViewRenderer;

final class ViewRendererRouteTest extends TestCase
{
    private ?Application $application = null;

    protected function setUp(): void
    {
        $this->application = new Application(
            configOverrides: ['view' => ['path' => dirname(__DIR__) . '/Fixtures/views']],
            basePath: dirname(__DIR__, 2),
        );
        $this->application->router->registerRoutesFromController(ViewRouteController::class);
        $this->application->instance(Request::class, new Request(server: [
            'HTTP_HOST' => 'green.test',
            'HTTPS' => 'on',
        ]));
    }

    protected function tearDown(): void
    {
        $this->application?->make(GreenErrorKernel::class)->unregister();
    }

    public function test_it_generates_a_named_route_with_parameters_in_twig(): void
    {
        $output = $this->application->make(ViewRenderer::class)->render(
            'route-link',
            ['user' => ['id' => 42]],
        );

        self::assertSame('<a href="https://green.test/users/42">View user</a>', trim($output));
    }

    public function test_it_reports_a_missing_named_route_parameter(): void
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('Missing required parameter [id]');

        $this->application->make(ViewRenderer::class)->render('route-missing-parameter');
    }
}

final class ViewRouteController
{
    #[Route('GET', '/users/{id}', name: 'users.show')]
    public function show(): void
    {
    }
}
