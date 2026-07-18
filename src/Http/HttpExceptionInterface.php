<?php

namespace YasserElgammal\Green\Http;

interface HttpExceptionInterface
{
    public function getStatusCode(): int;

    /** @return array<string, string> */
    public function getHeaders(): array;
}
