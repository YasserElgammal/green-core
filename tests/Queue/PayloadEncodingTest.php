<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Queue;

use JsonException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use YasserElgammal\Green\Queue\Contracts\JobInterface;
use YasserElgammal\Green\Queue\QueueManager;

final class PayloadEncodingTest extends TestCase
{
    public function testItRoundTripsAJobWithItsState(): void
    {
        $manager = new QueueManager([]);
        $original = new PayloadEncodingJob('stateful value');

        $payload = $manager->encode($original);
        $decoded = $manager->decode($payload);

        self::assertInstanceOf(PayloadEncodingJob::class, $decoded);
        self::assertNotSame($original, $decoded);
        self::assertSame('stateful value', $decoded->value);
        self::assertSame(PayloadEncodingJob::class, json_decode($payload, true)['class']);
    }

    public function testItRejectsMalformedJson(): void
    {
        $this->expectException(JsonException::class);
        (new QueueManager([]))->decode('{');
    }

    public function testItRejectsPayloadsMissingRequiredFields(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid queue payload format.');
        (new QueueManager([]))->decode('{}');
    }

    public function testItRejectsDecodedObjectsThatAreNotJobs(): void
    {
        $payload = json_encode([
            'class' => \stdClass::class,
            'data' => serialize(new \stdClass()),
        ], JSON_THROW_ON_ERROR);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Decoded job does not implement JobInterface');
        (new QueueManager([]))->decode($payload);
    }
}

final class PayloadEncodingJob implements JobInterface
{
    public function __construct(public readonly string $value) {}
    public function handle(): void {}
    public function maxAttempts(): int { return 1; }
    public function retryDelay(): int { return 0; }
}
