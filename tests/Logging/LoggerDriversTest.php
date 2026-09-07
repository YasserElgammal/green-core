<?php

namespace YasserElgammal\Green\Tests\Logging;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\ErrorHandling\ErrorRecord;
use YasserElgammal\Green\Logging\Drivers\DatabaseLogger;
use YasserElgammal\Green\Logging\Drivers\FileLogger;
use YasserElgammal\Green\Logging\LogLevel;

final class LoggerDriversTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/green_logs_' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function test_file_logger_creates_structured_log_entry_and_filters_levels(): void
    {
        $logger = new FileLogger($this->directory, LogLevel::WARNING);
        $record = $this->record(message: str_repeat('x', 220));

        self::assertFalse($logger->supports(LogLevel::INFO));
        self::assertTrue($logger->supports(LogLevel::ERROR));

        $logger->log($record);
        $contents = file_get_contents($this->directory . '/green-' . date('Y-m-d') . '.log');

        self::assertStringContainsString('ERR_test', $contents);
        self::assertStringContainsString(str_repeat('x', 197) . '...', $contents);
        self::assertStringContainsString('"context":{"request_id":"req-1"}', $contents);
    }

    public function test_database_logger_persists_normalized_record(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE error_logs (
            id TEXT PRIMARY KEY, level TEXT, type TEXT, message TEXT, file TEXT,
            line INTEGER, stack_trace TEXT, context TEXT, fingerprint TEXT, created_at TEXT
        )');
        $logger = new DatabaseLogger($connection);

        self::assertFalse($logger->supports(LogLevel::INFO));
        self::assertTrue($logger->supports(LogLevel::WARNING));

        $logger->log($this->record());
        $row = $connection->fetchAssociative('SELECT * FROM error_logs WHERE id = ?', ['ERR_test']);

        self::assertSame('error', $row['level']);
        self::assertSame('RuntimeException', $row['type']);
        self::assertSame('{"request_id":"req-1"}', $row['context']);
    }

    private function record(string $message = 'Something failed'): ErrorRecord
    {
        return new ErrorRecord(
            id: 'ERR_test',
            message: $message,
            type: 'RuntimeException',
            file: '/app/Test.php',
            line: 42,
            stackTrace: '#0 test',
            context: ['request_id' => 'req-1'],
            level: LogLevel::ERROR,
            timestamp: 1_788_825_600.0,
            fingerprint: 'abc123',
        );
    }
}
