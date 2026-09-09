<?php

namespace YasserElgammal\Green\Tests\Signal;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use YasserElgammal\Green\Container\Container;
use YasserElgammal\Green\Signal\SignalDispatcher;

class SignalDispatcherTest extends TestCase
{
    private SignalDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new SignalDispatcher();
    }

    public function test_it_registers_and_emits_signals()
    {
        $fired = false;

        $this->dispatcher->listen('user.created', function (array $payload) use (&$fired) {
            $fired = true;
            $this->assertEquals(42, $payload['id']);
            return 'success';
        });

        $results = $this->dispatcher->emit('user.created', ['id' => 42]);

        $this->assertTrue($fired);
        $this->assertEquals(['success'], $results);
    }

    public function test_listeners_execute_in_priority_order()
    {
        $order = [];

        // Lower numbers execute earlier
        $this->dispatcher->listen('order.placed', function () use (&$order) {
            $order[] = 'third';
        }, priority: 10);

        $this->dispatcher->listen('order.placed', function () use (&$order) {
            $order[] = 'first';
        }, priority: 0);

        $this->dispatcher->listen('order.placed', function () use (&$order) {
            $order[] = 'second';
        }, priority: 5);

        $this->dispatcher->emit('order.placed');

        $this->assertEquals(['first', 'second', 'third'], $order);
    }

    public function test_propagation_halts_when_listener_returns_false()
    {
        $order = [];

        $this->dispatcher->listen('test.halt', function () use (&$order) {
            $order[] = 'first';
            return false; // Halt propagation
        }, priority: 0);

        $this->dispatcher->listen('test.halt', function () use (&$order) {
            $order[] = 'second';
        }, priority: 10);

        $results = $this->dispatcher->emit('test.halt');

        $this->assertEquals(['first'], $order);
        $this->assertEquals([false], $results);
    }

    public function test_has_listeners_and_forget()
    {
        $this->assertFalse($this->dispatcher->hasListeners('test.event'));

        $this->dispatcher->listen('test.event', fn() => true);
        $this->assertTrue($this->dispatcher->hasListeners('test.event'));

        $this->dispatcher->forget('test.event');
        $this->assertFalse($this->dispatcher->hasListeners('test.event'));
    }

    public function test_it_resolves_invokable_listener_classes_from_the_container(): void
    {
        $container = new Container();
        $container->instance(SignalListenerDependency::class, new SignalListenerDependency('resolved'));
        $dispatcher = new SignalDispatcher(fn (string $listener) => $container->make($listener));
        $dispatcher->listen('container.listener', ContainerResolvedSignalListener::class);

        self::assertSame(
            ['resolved:42'],
            $dispatcher->emit('container.listener', ['id' => 42]),
        );
    }

    public function test_it_rejects_a_listener_class_that_is_not_callable(): void
    {
        $container = new Container();
        $dispatcher = new SignalDispatcher(fn (string $listener) => $container->make($listener));
        $dispatcher->listen('invalid.listener', NonCallableSignalListener::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be callable');

        $dispatcher->emit('invalid.listener');
    }
}

final readonly class SignalListenerDependency
{
    public function __construct(public string $value)
    {
    }
}

final readonly class ContainerResolvedSignalListener
{
    public function __construct(private SignalListenerDependency $dependency)
    {
    }

    public function __invoke(array $payload): string
    {
        return $this->dependency->value . ':' . $payload['id'];
    }
}

final class NonCallableSignalListener
{
}
