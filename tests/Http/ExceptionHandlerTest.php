<?php

namespace YasserElgammal\Green\Tests\Http;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use YasserElgammal\Green\ErrorHandling\ErrorRecord;
use YasserElgammal\Green\Exceptions\ExceptionHandler;
use YasserElgammal\Green\Http\HttpExceptionInterface;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Logging\LoggerInterface;
use YasserElgammal\Green\Logging\LogLevel;
use YasserElgammal\Green\Logging\LogManager;

class ExceptionHandlerTest extends TestCase
{
    private mixed $previousDebug;
    private bool $hadDebug;

    protected function setUp(): void
    {
        $this->hadDebug = array_key_exists('APP_DEBUG', $_ENV);
        $this->previousDebug = $_ENV['APP_DEBUG'] ?? null;
        $_ENV['APP_DEBUG'] = 'false';
    }

    protected function tearDown(): void
    {
        if ($this->hadDebug) {
            $_ENV['APP_DEBUG'] = $this->previousDebug;
        } else {
            unset($_ENV['APP_DEBUG']);
        }
    }

    public function testProductionJsonResponseDoesNotLeakExceptionDetails(): void
    {
        $response = (new ExceptionHandler())->handle(
            new RuntimeException('SQL password=secret', 503),
            $this->jsonRequest('/api/users'),
        );

        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeader('Content-Type'));
        self::assertSame('An unexpected error occurred.', $payload['message']);
        self::assertStringStartsWith('ERR_', $payload['trace_id']);
        self::assertStringNotContainsString('secret', $response->getContent());
    }

    public function testExplicitHttpExceptionControlsStatusAndHeaders(): void
    {
        $response = (new ExceptionHandler())->handle(
            new TestHttpException(429, ['Retry-After' => '60']),
            $this->jsonRequest('/resource'),
        );

        self::assertSame(429, $response->getStatusCode());
        self::assertSame('60', $response->getHeader('Retry-After'));
    }

    public function testResponseTraceIdMatchesLoggedRecord(): void
    {
        $logger = new RecordingLogger();
        $manager = new LogManager();
        $manager->addDriver($logger);

        $response = (new ExceptionHandler($manager))->handle(
            new RuntimeException('failure'),
            $this->jsonRequest('/api/failure'),
        );
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertNotNull($logger->record);
        self::assertSame($logger->record->id, $payload['trace_id']);
    }

    public function testJsonContentTypeWithVendorSuffixIsRecognized(): void
    {
        $request = new Request(server: [
            'REQUEST_URI' => '/resource',
            'CONTENT_TYPE' => 'application/problem+json',
        ]);

        $response = (new ExceptionHandler())->handle(new RuntimeException('failure'), $request);

        self::assertSame('application/json', $response->getHeader('Content-Type'));
    }

    private function jsonRequest(string $path): Request
    {
        return new Request(server: [
            'REQUEST_URI' => $path,
            'HTTP_ACCEPT' => 'application/json',
        ]);
    }
}

final class TestHttpException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(
        private readonly int $statusCode,
        private readonly array $headers = [],
    ) {
        parent::__construct('HTTP failure');
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}

final class RecordingLogger implements LoggerInterface
{
    public ?ErrorRecord $record = null;

    public function log(ErrorRecord $record): void
    {
        $this->record = $record;
    }

    public function supports(LogLevel $level): bool
    {
        return true;
    }
}
