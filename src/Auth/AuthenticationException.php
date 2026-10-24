<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Auth;

use RuntimeException;
use YasserElgammal\Green\Http\HttpExceptionInterface;

final class AuthenticationException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(string $message = 'Authentication is required.')
    {
        parent::__construct($message, 401);
    }

    public function getStatusCode(): int
    {
        return 401;
    }

    public function getHeaders(): array
    {
        return [];
    }
}
