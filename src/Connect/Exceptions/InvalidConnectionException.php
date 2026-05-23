<?php

namespace YasserElgammal\Green\Connect\Exceptions;

class InvalidConnectionException extends ConnectException
{
    public function __construct(string $connection)
    {
        parent::__construct("Connect connection [{$connection}] is not configured.");
    }
}
