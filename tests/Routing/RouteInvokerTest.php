<?php

namespace YasserElgammal\Green\Tests\Routing;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Routing\RouteInvoker;

class RouteInvokerTest extends TestCase
{
    public function test_it_resolves_action_service_dependencies(): void
    {
        $response = (new RouteInvoker())->invoke(
            [InvokerController::class, 'show'],
            new Request(),
            ['id' => '15']
        );

        $this->assertSame('15:resolved', $response->getContent());
    }

    public function test_it_rejects_unresolvable_action_parameters(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot resolve controller action parameter [$missing]');

        (new RouteInvoker())->invoke(
            [InvokerController::class, 'missing'],
            new Request(),
            []
        );
    }
}

final class InvokerController
{
    public function show(string $id, InvokerActionDependency $dependency): string
    {
        return $id . ':' . $dependency->value();
    }

    public function missing(string $missing): string
    {
        return $missing;
    }
}

final class InvokerActionDependency
{
    public function value(): string
    {
        return 'resolved';
    }
}
