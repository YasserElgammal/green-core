<?php

namespace YasserElgammal\Green\Connect\Contracts;

interface ConnectDriverInterface
{
    /**
     * Send an outgoing HTTP request.
     *
     * @param array{
     *     headers?: array<string,string>,
     *     query?: array<string,mixed>,
     *     body?: mixed,
     *     json?: mixed,
     *     form?: array<string,mixed>,
     *     timeout?: int|float,
     *     connect_timeout?: int|float
     * } $options
     */
    public function send(string $method, string $url, array $options = []): ConnectResponseInterface;
}
