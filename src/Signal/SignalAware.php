<?php

namespace YasserElgammal\Green\Signal;

trait SignalAware
{
    /**
     * Emit a signal prefixed with the current class name.
     *
     * @param string $signal The specific signal name
     * @param array $payload Data to pass to listeners
     * @return array Results from listeners
     */
    protected function emitSignal(string $signal, array $payload = []): array
    {
        return signal()->emit(static::class . '.' . $signal, $payload);
    }
}
