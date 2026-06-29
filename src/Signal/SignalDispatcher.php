<?php

namespace YasserElgammal\Green\Signal;

use SplPriorityQueue;

class SignalDispatcher
{
    /**
     * @var array<string, SplPriorityQueue<int, callable>>
     */
    private array $listeners = [];

    /**
     * Register a listener for a given signal.
     * Lower priority numbers execute earlier.
     *
     * @param string $signal The name of the signal
     * @param callable $listener The callback to execute
     * @param int $priority Priority for execution order (default: 0)
     */
    public function listen(string $signal, callable $listener, int $priority = 0): void
    {
        if (!isset($this->listeners[$signal])) {
            // SplPriorityQueue is max-heap, so higher values are extracted first.
            // To make lower numbers execute *earlier* (like 0 before 10), we insert negative priority.
            // Or we just accept higher numbers execute earlier.
            // Let's implement it such that higher numbers execute first (standard priority).
            // Wait, the plan said "lower = earlier".
            // If "lower = earlier", we use negative priority in the queue.
            $this->listeners[$signal] = new SplPriorityQueue();
        }

        // To make lower = earlier, we insert with negative priority
        $this->listeners[$signal]->insert($listener, -$priority);
    }

    /**
     * Emit a signal to all registered listeners.
     *
     * @param string $signal The name of the signal
     * @param array $payload Data to pass to listeners
     * @return array The return values from the listeners
     */
    public function emit(string $signal, array $payload = []): array
    {
        if (!isset($this->listeners[$signal])) {
            return [];
        }

        $results = [];

        // Clone the queue so that emitting doesn't empty the registered listeners
        $queue = clone $this->listeners[$signal];

        foreach ($queue as $listener) {
            $result = $listener($payload);
            $results[] = $result;

            if ($result === false) {
                break; // Halt propagation
            }
        }

        return $results;
    }

    /**
     * Remove all listeners for a given signal.
     */
    public function forget(string $signal): void
    {
        unset($this->listeners[$signal]);
    }

    /**
     * Check if a signal has any listeners registered.
     */
    public function hasListeners(string $signal): bool
    {
        return isset($this->listeners[$signal]) && !$this->listeners[$signal]->isEmpty();
    }
}
