<?php

namespace YasserElgammal\Green\Connect\Drivers;

use YasserElgammal\Green\Connect\ConnectResponse;
use YasserElgammal\Green\Connect\Contracts\ConnectDriverInterface;

final class FakeDriver implements ConnectDriverInterface
{
    /**
     * @var array<int, ConnectResponse|\Throwable>
     */
    private array $queue = [];

    /**
     * @var array<int,array{method:string,url:string,options:array<string,mixed>}>
     */
    private array $requests = [];

    public function send(string $method, string $url, array $options = []): ConnectResponse
    {
        $this->requests[] = [
            'method' => strtoupper($method),
            'url' => $url,
            'options' => $options,
        ];

        $next = array_shift($this->queue) ?? new ConnectResponse(200, '');

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    /**
     * @param array<string,string> $headers
     */
    public function respond(string $body = '', int $status = 200, array $headers = []): self
    {
        $this->queue[] = new ConnectResponse($status, $body, $headers);

        return $this;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $headers
     */
    public function respondJson(array $data = [], int $status = 200, array $headers = []): self
    {
        return $this->respond(
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            $status,
            array_merge(['Content-Type' => 'application/json'], $headers),
        );
    }

    public function throw(\Throwable $exception): self
    {
        $this->queue[] = $exception;

        return $this;
    }

    /**
     * @return array<int,array{method:string,url:string,options:array<string,mixed>}>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    public function assertSent(string|callable $method, ?string $path = null): void
    {
        foreach ($this->requests as $request) {
            if (is_callable($method) && $method($request)) {
                return;
            }

            if (is_string($method) && strtoupper($method) === $request['method']) {
                if ($path === null || $this->pathMatches($request['url'], $path)) {
                    return;
                }
            }
        }

        $this->fail('Expected an outgoing HTTP request to be sent, but none matched.');
    }

    public function assertNothingSent(): void
    {
        if ($this->requests !== []) {
            $this->fail('Expected no outgoing HTTP requests to be sent, but found ' . count($this->requests) . '.');
        }
    }

    public function assertSentCount(int $expected): void
    {
        $actual = count($this->requests);

        if ($actual !== $expected) {
            $this->fail("Expected {$expected} outgoing HTTP requests, but found {$actual}.");
        }
    }

    public function flush(): void
    {
        $this->queue = [];
        $this->requests = [];
    }

    private function pathMatches(string $url, string $expected): bool
    {
        if ($url === $expected) {
            return true;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        return $path === $expected;
    }

    private function fail(string $message): void
    {
        if (class_exists(\PHPUnit\Framework\Assert::class)) {
            \PHPUnit\Framework\Assert::fail($message);
        }

        throw new \RuntimeException($message);
    }
}
