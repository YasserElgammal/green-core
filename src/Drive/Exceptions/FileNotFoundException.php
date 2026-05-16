<?php

namespace YasserElgammal\Green\Drive\Exceptions;

/**
 * Thrown when a requested file does not exist on the storage disk.
 *
 * Includes the relative path that was looked up so callers can
 * provide meaningful error messages without leaking the absolute
 * storage root.
 */
class FileNotFoundException extends \RuntimeException
{
    public function __construct(
        public readonly string $path,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct("File not found: {$path}", $code, $previous);
    }
}
