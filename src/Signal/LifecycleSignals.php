<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Signal;

final class LifecycleSignals
{
    public const REQUEST_RECEIVED = 'request.received';
    public const REQUEST_HANDLED = 'request.handled';
    public const EXCEPTION_OCCURRED = 'exception.occurred';
    public const COMMAND_STARTING = 'command.starting';
    public const COMMAND_FINISHED = 'command.finished';

    private function __construct()
    {
    }
}
