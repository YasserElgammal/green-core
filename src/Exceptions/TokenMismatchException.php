<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Exceptions;

class TokenMismatchException extends \RuntimeException
{
    public readonly int $statusCode;

    public function __construct(
        string $message = 'CSRF token mismatch.',
        int $statusCode = 419,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
