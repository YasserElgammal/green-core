<?php

namespace YasserElgammal\Green\Tests\Routing;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Routing\Route;
use YasserElgammal\Green\Routing\RouteRegistrar;
use YasserElgammal\Green\Routing\RouteRegistry;

class RouteRegistrarTest extends TestCase
{
    public function test_it_registers_attribute_routes_into_the_registry(): void
    {
        $registry = new RouteRegistry();
        $registrar = new RouteRegistrar($registry);

        $registrar->registerRoutesFromController(RegistrarController::class);

        $routes = $registry->all();

        $this->assertCount(2, $routes);
        $this->assertSame('GET', $routes[0]['method']);
        $this->assertSame('POST', $routes[1]['method']);
        $this->assertSame('/registrar', $routes[0]['path']);
        $this->assertSame('/registrar', $registry->getNamedRoute('registrar.store'));
    }
}

final class RegistrarController
{
    #[Route(['GET', 'POST'], '/registrar', ['auth'], name: 'registrar.store')]
    public function store(): void
    {
    }
}