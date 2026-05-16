<?php

namespace YasserElgammal\Green\Logging;

/**
 * Log severity levels ordered from least to most severe.
 *
 * Used by LogManager and drivers to filter which errors to process.
 */
enum LogLevel: string
{
    case DEBUG    = 'debug';
    case INFO     = 'info';
    case WARNING  = 'warning';
    case ERROR    = 'error';
    case CRITICAL = 'critical';

    /**
     * Numeric severity (0 = least severe, 4 = most severe).
     *
     * Drivers use this to implement minimum-level filtering:
     * a driver with minimumLevel=WARNING will skip DEBUG and INFO.
     */
    public function severity(): int
    {
        return match ($this) {
            self::DEBUG    => 0,
            self::INFO     => 1,
            self::WARNING  => 2,
            self::ERROR    => 3,
            self::CRITICAL => 4,
        };
    }

    /**
     * Map a PHP error severity constant (E_NOTICE, E_WARNING, etc.)
     * to the appropriate LogLevel.
     */
    public static function fromPhpSeverity(int $severity): self
    {
        return match ($severity) {
            E_NOTICE, E_USER_NOTICE, E_STRICT, E_DEPRECATED, E_USER_DEPRECATED => self::WARNING,
            E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING       => self::WARNING,
            E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE                    => self::CRITICAL,
            E_USER_ERROR, E_RECOVERABLE_ERROR                                  => self::ERROR,
            default                                                            => self::ERROR,
        };
    }

    /**
     * Human-readable name of a PHP error constant.
     */
    public static function phpSeverityName(int $severity): string
    {
        return match ($severity) {
            E_ERROR             => 'E_ERROR',
            E_WARNING           => 'E_WARNING',
            E_PARSE             => 'E_PARSE',
            E_NOTICE            => 'E_NOTICE',
            E_CORE_ERROR        => 'E_CORE_ERROR',
            E_CORE_WARNING      => 'E_CORE_WARNING',
            E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
            E_USER_ERROR        => 'E_USER_ERROR',
            E_USER_WARNING      => 'E_USER_WARNING',
            E_USER_NOTICE       => 'E_USER_NOTICE',
            E_STRICT            => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED        => 'E_DEPRECATED',
            E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
            default             => 'UNKNOWN_ERROR_' . $severity,
        };
    }
}
