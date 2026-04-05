<?php

namespace YasserElgammal\Green\Middleware;

use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
