<?php

namespace YasserElgammal\Green;

use YasserElgammal\Green\Routing\Router;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Http\JsonResponse;
use YasserElgammal\Green\Http\ValidationException;

class Application
{
    public Router $router;

    public function __construct()
    {
        $this->router = new Router();
    }

    public function handle(Request $request): Response
    {
        return $this->router->dispatch($request);
    }
}
