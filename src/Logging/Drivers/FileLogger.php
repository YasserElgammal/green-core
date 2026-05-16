<?php

namespace YasserElgammal\Green\Logging\Drivers;

use YasserElgammal\Green\ErrorHandling\ErrorRecord;
use YasserElgammal\Green\Logging\LoggerInterface;
use YasserElgammal\Green\Logging\LogLevel;

/**
 * Default logging driver — writes structured log entries to date-partitioned files.
 *
 * Log format (one entry = two lines):
 *   [2026-05-16 14:30:00] ERROR | ERR_abc123 | Error message | /app/File.php:42 | fingerprint
 *   {"context":{"url":"...","method":"GET",...},"stack_trace":"..."}
 *
 * Files are named: green-YYYY-MM-DD.log
 * A new file is created each day for easy rotation and archival.
 */
final class FileLogger implements LoggerInterface
{
    public function __construct(
        private readonly string $logDirectory,
        private readonly LogLevel $minimumLevel = LogLevel::DEBUG,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function log(ErrorRecord $record): void
    {
        $this->ensureDirectoryExists();

        $filename = $this->logDirectory . '/green-' . date('Y-m-d') . '.log';

        // --- Line 1: structured summary ---
        $datetime = date('Y-m-d H:i:s', (int) $record->timestamp);
        $level    = strtoupper($record->level->value);

        $summary = sprintf(
            "[%s] %s | %s | %s | %s:%d | %s",
            $datetime,
            str_pad($level, 8),
            $record->id,
            $this->truncate($record->message, 200),
            $record->file,
            $record->line,
            $record->fingerprint
        );

        // --- Line 2: JSON payload with context + trace ---
        $payload = json_encode([
            'type'        => $record->type,
            'context'     => $record->context,
            'stack_trace' => $record->stackTrace,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $entry = $summary . "\n" . $payload . "\n\n";

        file_put_contents($filename, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * {@inheritDoc}
     */
    public function supports(LogLevel $level): bool
    {
        return $level->severity() >= $this->minimumLevel->severity();
    }

    /**
     * Create the log directory if it doesn't exist.
     */
    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->logDirectory)) {
            mkdir($this->logDirectory, 0755, true);
        }
    }

    /**
     * Truncate a string to a maximum length, appending "..." if truncated.
     */
    private function truncate(string $value, int $maxLength): string
    {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength - 3) . '...';
    }
}
