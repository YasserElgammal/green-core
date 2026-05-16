<?php

namespace YasserElgammal\Green\Logging\Drivers;

use Doctrine\DBAL\Connection;
use YasserElgammal\Green\ErrorHandling\ErrorRecord;
use YasserElgammal\Green\Logging\LoggerInterface;
use YasserElgammal\Green\Logging\LogLevel;

/**
 * Optional logging driver — persists error records to a database table.
 *
 * Requires Doctrine DBAL (already a project dependency).
 * Defaults to minimum level WARNING to avoid flooding the database
 * with debug/info entries.
 *
 * Expected table schema:
 *
 *   CREATE TABLE error_logs (
 *       id          VARCHAR(32)  PRIMARY KEY,
 *       level       VARCHAR(10)  NOT NULL,
 *       type        VARCHAR(255) NOT NULL,
 *       message     TEXT         NOT NULL,
 *       file        VARCHAR(500) NOT NULL,
 *       line        INT          NOT NULL,
 *       stack_trace TEXT,
 *       context     JSON,
 *       fingerprint VARCHAR(32)  NOT NULL,
 *       created_at  DATETIME     NOT NULL,
 *       INDEX idx_fingerprint (fingerprint),
 *       INDEX idx_created_at (created_at),
 *       INDEX idx_level (level)
 *   );
 */
final class DatabaseLogger implements LoggerInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $tableName = 'error_logs',
        private readonly LogLevel $minimumLevel = LogLevel::WARNING,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function log(ErrorRecord $record): void
    {
        $this->connection->insert($this->tableName, [
            'id'          => $record->id,
            'level'       => $record->level->value,
            'type'        => $record->type,
            'message'     => $this->truncate($record->message, 65535),
            'file'        => $this->truncate($record->file, 500),
            'line'        => $record->line,
            'stack_trace' => $record->stackTrace,
            'context'     => json_encode($record->context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'fingerprint' => $record->fingerprint,
            'created_at'  => date('Y-m-d H:i:s', (int) $record->timestamp),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function supports(LogLevel $level): bool
    {
        return $level->severity() >= $this->minimumLevel->severity();
    }

    /**
     * Truncate a string to fit column constraints.
     */
    private function truncate(string $value, int $maxLength): string
    {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength - 3) . '...';
    }
}
