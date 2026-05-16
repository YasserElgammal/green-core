<?php

namespace YasserElgammal\Green\Drive\Exceptions;

/**
 * Thrown when a file path fails security validation.
 *
 * Triggers include:
 *  - Path traversal attempts (../)
 *  - Null byte injection (\0)
 *  - Control characters
 *  - Paths that escape the configured root directory
 */
class InvalidPathException extends \RuntimeException
{
    public function __construct(
        public readonly string $path,
        string $reason = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $message = "Invalid path: {$path}";
        if ($reason !== '') {
            $message .= " ({$reason})";
        }

        parent::__construct($message, $code, $previous);
    }
}
