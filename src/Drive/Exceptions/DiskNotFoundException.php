<?php

namespace YasserElgammal\Green\Drive\Exceptions;

/**
 * Thrown when a requested disk name is not defined in the drive configuration.
 */
class DiskNotFoundException extends \RuntimeException
{
    public function __construct(
        public readonly string $diskName,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct("Disk not found: '{$diskName}'. Check your config/drive.php configuration.", $code, $previous);
    }
}
