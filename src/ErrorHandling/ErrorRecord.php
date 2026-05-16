<?php

namespace YasserElgammal\Green\ErrorHandling;

use Throwable;
use YasserElgammal\Green\Logging\LogLevel;

/**
 * Immutable value object that normalizes any error source into a unified structure.
 *
 * Every error in the system — whether an uncaught Exception, a PHP warning,
 * or a fatal error — is converted into an ErrorRecord before being passed
 * to the logging layer. This guarantees a consistent shape for all drivers.
 */
final class ErrorRecord
{
    public function __construct(
        /** Unique error identifier (e.g. ERR_682...) */
        public readonly string $id,

        /** Human-readable error message */
        public readonly string $message,

        /** Error type: Exception FQCN or PHP error constant name */
        public readonly string $type,

        /** File where the error occurred */
        public readonly string $file,

        /** Line number where the error occurred */
        public readonly int $line,

        /** Full stack trace as a string */
        public readonly string $stackTrace,

        /** Request context (URL, method, headers, user_id, etc.) */
        public readonly array $context,

        /** Severity level */
        public readonly LogLevel $level,

        /** Unix timestamp with microseconds */
        public readonly float $timestamp,

        /** md5 hash of type+message+file+line for deduplication */
        public readonly string $fingerprint,
    ) {
    }

    /**
     * Create an ErrorRecord from an uncaught Exception or Error.
     */
    public static function fromException(Throwable $e, array $context = []): self
    {
        $type = get_class($e);

        return new self(
            id:          self::generateId(),
            message:     $e->getMessage(),
            type:        $type,
            file:        $e->getFile(),
            line:        $e->getLine(),
            stackTrace:  $e->getTraceAsString(),
            context:     $context,
            level:       LogLevel::ERROR,
            timestamp:   microtime(true),
            fingerprint: self::buildFingerprint($type, $e->getMessage(), $e->getFile(), $e->getLine()),
        );
    }

    /**
     * Create an ErrorRecord from a PHP error (warning, notice, etc.)
     * captured by set_error_handler.
     */
    public static function fromPhpError(int $severity, string $message, string $file, int $line, array $context = []): self
    {
        $type = LogLevel::phpSeverityName($severity);

        return new self(
            id:          self::generateId(),
            message:     $message,
            type:        $type,
            file:        $file,
            line:        $line,
            stackTrace:  self::captureCurrentTrace(),
            context:     $context,
            level:       LogLevel::fromPhpSeverity($severity),
            timestamp:   microtime(true),
            fingerprint: self::buildFingerprint($type, $message, $file, $line),
        );
    }

    /**
     * Create an ErrorRecord from a fatal error captured by
     * register_shutdown_function + error_get_last().
     */
    public static function fromFatalError(array $error, array $context = []): self
    {
        $type = LogLevel::phpSeverityName($error['type'] ?? E_ERROR);

        return new self(
            id:          self::generateId(),
            message:     $error['message'] ?? 'Unknown fatal error',
            type:        $type,
            file:        $error['file'] ?? 'unknown',
            line:        $error['line'] ?? 0,
            stackTrace:  '', // Fatal errors don't provide a trace
            context:     $context,
            level:       LogLevel::CRITICAL,
            timestamp:   microtime(true),
            fingerprint: self::buildFingerprint(
                $type,
                $error['message'] ?? '',
                $error['file'] ?? '',
                $error['line'] ?? 0
            ),
        );
    }

    /**
     * Create an ErrorRecord from a manual log call (green_log helper).
     */
    public static function fromManual(string $message, LogLevel $level, array $context = []): self
    {
        // Walk up the backtrace to find the caller (skip this method + the helper)
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        $caller = $trace[2] ?? $trace[1] ?? $trace[0] ?? [];

        $file = $caller['file'] ?? 'unknown';
        $line = $caller['line'] ?? 0;

        return new self(
            id:          self::generateId(),
            message:     $message,
            type:        'ManualLog',
            file:        $file,
            line:        $line,
            stackTrace:  self::captureCurrentTrace(),
            context:     $context,
            level:       $level,
            timestamp:   microtime(true),
            fingerprint: self::buildFingerprint('ManualLog', $message, $file, $line),
        );
    }

    /**
     * Convert the record to an associative array (for JSON serialization / DB insert).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'message'     => $this->message,
            'type'        => $this->type,
            'file'        => $this->file,
            'line'        => $this->line,
            'stack_trace' => $this->stackTrace,
            'context'     => $this->context,
            'level'       => $this->level->value,
            'timestamp'   => $this->timestamp,
            'fingerprint' => $this->fingerprint,
            'created_at'  => date('Y-m-d H:i:s', (int) $this->timestamp),
        ];
    }

    /**
     * Generate a unique error ID.
     */
    private static function generateId(): string
    {
        return 'ERR_' . bin2hex(random_bytes(12));
    }

    /**
     * Build a deduplication fingerprint from the error's identity fields.
     */
    private static function buildFingerprint(string $type, string $message, string $file, int $line): string
    {
        return md5("{$type}|{$message}|{$file}|{$line}");
    }

    /**
     * Capture the current call stack as a string (for non-exception errors).
     */
    private static function captureCurrentTrace(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);

        // Remove the first few frames (this method + ErrorRecord internals)
        $trace = array_slice($trace, 3);

        $lines = [];
        foreach ($trace as $i => $frame) {
            $file     = $frame['file'] ?? '[internal]';
            $line     = $frame['line'] ?? 0;
            $class    = $frame['class'] ?? '';
            $function = $frame['function'] ?? '';
            $type     = $frame['type'] ?? '';

            $lines[] = "#{$i} {$file}({$line}): {$class}{$type}{$function}()";
        }

        return implode("\n", $lines);
    }
}
