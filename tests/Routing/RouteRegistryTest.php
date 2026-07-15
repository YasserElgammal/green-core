<?php

namespace YasserElgammal\Green\Tests\Routing;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Routing\RouteRegistry;

class RouteRegistryTest extends TestCase
{
    public function test_it_stores_routes_and_named_routes(): void
    {
        $registry = new RouteRegistry();

        $registry->add('get', '/users/{id}', [RegistryController::class, 'show'], ['auth'], 'users.show');

        $routes = $registry->all();

        $this->assertSame('GET', $routes[0]['method']);
        $this->assertSame('/users/{id}', $routes[0]['path']);
        $this->assertSame([RegistryController::class, 'show'], $routes[0]['handler']);
        $this->assertSame(['auth'], $routes[0]['middleware']);
        $this->assertSame('/users/{id}', $registry->getNamedRoute('users.show'));
    }
}

final class RegistryController
{
    public function show(): void
    {
    }
}