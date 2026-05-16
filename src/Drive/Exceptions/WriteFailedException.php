<?php

namespace YasserElgammal\Green\Drive\Exceptions;

/**
 * Thrown when a write or delete operation fails on the storage backend.
 *
 * Common causes: permission denied, disk full, or driver-specific errors.
 */
class WriteFailedException extends \RuntimeException
{
    public function __construct(
        public readonly string $path,
        string $operation = 'write',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            "Failed to {$operation} file: {$path}" . ($previous ? " — {$previous->getMessage()}" : ''),
            $code,
            $previous,
        );
    }
}
