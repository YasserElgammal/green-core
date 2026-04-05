<?php

namespace YasserElgammal\Green\Http;

class RedirectResponse extends Response
{
    public function __construct(string $url, int $statusCode = 302)
    {
        parent::__construct('', $statusCode);
        $this->setHeader('Location', $url);
    }
}
