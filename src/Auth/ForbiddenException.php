<?php

namespace YasserElgammal\Green\Auth;

use RuntimeException;
use YasserElgammal\Green\Http\HttpExceptionInterface;

class ForbiddenException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(
        public readonly string $ability,
        public readonly string $subjectType,
        string $message = 'This action is unauthorized.'
    ) {
        parent::__construct($message, 403);
    }

    public function getStatusCode(): int
    {
        return 403;
    }

    public function getHeaders(): array
    {
        return [];
    }
}
