<?php

namespace YasserElgammal\Green\Connect\Exceptions;

use YasserElgammal\Green\Connect\ConnectResponse;

class RequestException extends ConnectException
{
    public function __construct(
        private readonly ConnectResponse $response,
        ?string $message = null,
    ) {
        parent::__construct($message ?? 'HTTP request failed with status ' . $response->status(), $response->status());
    }

    public function response(): ConnectResponse
    {
        return $this->response;
    }
}
