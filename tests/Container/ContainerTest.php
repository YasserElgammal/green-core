<?php

namespace YasserElgammal\Green\Tests\Container;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Container\BindingException;
use YasserElgammal\Green\Container\Container;
use YasserElgammal\Green\Container\NotFoundException;

interface ContainerDependency {}

final class ContainerDependencyImplementation implements ContainerDependency {}

final class ContainerConsumer
{
    public function __construct(public ContainerDependency $dependency) {}
}

final class ContainerWithDefault
{
    public function __construct(public string $value = 'default') {}
}

final class CircularA
{
    public function __construct(public CircularB $dependency) {}
}

final class CircularB
{
    public function __construct(public CircularA $dependency) {}
}

final class ContainerTest extends TestCase
{
    public function test_it_auto_wires_concrete_dependencies(): void
    {
        $container = new Container();
        $container->bind(ContainerDependency::class, ContainerDependencyImplementation::class);

        $consumer = $container->make(ContainerConsumer::class);

        self::assertInstanceOf(ContainerDependencyImplementation::class, $consumer->dependency);
    }

    public function test_transient_and_singleton_lifetimes_are_distinct(): void
    {
        $container = new Container();
        $container->bind('transient', fn () => new \stdClass());
        $container->singleton('singleton', fn () => new \stdClass());

        self::assertNotSame($container->make('transient'), $container->make('transient'));
        self::assertSame($container->make('singleton'), $container->make('singleton'));
    }

    public function test_rebinding_discards_a_resolved_singleton(): void
    {
        $container = new Container();
        $container->singleton('service', fn () => (object) ['version' => 1]);
        $first = $container->make('service');

        $container->singleton('service', fn () => (object) ['version' => 2]);

        self::assertNotSame($first, $container->make('service'));
        self::assertSame(2, $container->make('service')->version);
    }

    public function test_constructor_defaults_are_used(): void
    {
        $resolved = (new Container())->make(ContainerWithDefault::class);

        self::assertSame('default', $resolved->value);
    }

    public function test_it_detects_circular_dependencies(): void
    {
        $this->expectException(BindingException::class);
        $this->expectExceptionMessage('Circular dependency');

        (new Container())->make(CircularA::class);
    }

    public function test_failed_resolution_does_not_poison_a_later_retry(): void
    {
        $container = new Container();

        try {
            $container->make(ContainerConsumer::class);
            self::fail('The missing interface binding should fail.');
        } catch (NotFoundException) {
            // Expected; retry after supplying the missing binding.
        }

        $container->bind(ContainerDependency::class, ContainerDependencyImplementation::class);

        self::assertInstanceOf(ContainerConsumer::class, $container->make(ContainerConsumer::class));
    }
}