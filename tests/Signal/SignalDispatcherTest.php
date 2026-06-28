<?php

namespace YasserElgammal\Green\Tests\Signal;

use PHPUnit\Framework\TestCase;
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
}
