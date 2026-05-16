<?php

namespace YasserElgammal\Green\Logging;

use YasserElgammal\Green\ErrorHandling\ErrorRecord;

/**
 * Contract for all logging drivers.
 *
 * Each driver decides how and where to persist error records.
 * Drivers can optionally filter by log level via supports().
 */
interface LoggerInterface
{
    /**
     * Persist the given error record.
     *
     * Implementations MUST NOT throw exceptions — any internal failure
     * should be handled gracefully (e.g. fallback to error_log()).
     */
    public function log(ErrorRecord $record): void;

    /**
     * Whether this driver should handle errors at the given level.
     *
     * Returning false causes LogManager to skip this driver for that record.
     * This enables driver-level filtering (e.g. DatabaseLogger only for WARNING+).
     */
    public function supports(LogLevel $level): bool;
}
