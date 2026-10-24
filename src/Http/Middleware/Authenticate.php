<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Http\Middleware;

use YasserElgammal\Green\Auth\Auth;
use YasserElgammal\Green\Auth\AuthenticationException;
use YasserElgammal\Green\Auth\Authenticator;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Middleware\MiddlewareInterface;

final class Authenticate implements MiddlewareInterface
{
    public function __construct(
        private readonly Authenticator $authenticator,
        private readonly bool $required = true,
        private readonly ?string $context = null,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = auth()->resolve($request, $this->authenticator, $this->context);

        if ($this->required && $user === null) {
            throw new AuthenticationException();
        }

        return $next($request);
    }
}
