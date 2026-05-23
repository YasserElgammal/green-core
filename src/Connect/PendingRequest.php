<?php

namespace YasserElgammal\Green\Connect;

use YasserElgammal\Green\Connect\Contracts\ConnectResponseInterface;
use YasserElgammal\Green\Connect\Exceptions\ConnectionException;
use YasserElgammal\Green\Connect\Support\Headers;
use YasserElgammal\Green\Connect\Support\UrlBuilder;

final class PendingRequest
{
    /**
     * @param array<string,mixed> $connectionConfig
     * @param array<string,mixed> $options
     */
    public function __construct(
        private readonly ConnectManager $manager,
        private readonly string $connection,
        private readonly array $connectionConfig,
        private array $options = [],
    ) {
    }

    /**
     * @param array<string,string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->replaceOptions([
            'headers' => array_merge($this->headers(), Headers::normalize($headers)),
        ]);

        return $clone;
    }

    public function withHeader(string $name, string $value): self
    {
        return $this->withHeaders([$name => $value]);
    }

    public function acceptJson(): self
    {
        return $this->withHeader('Accept', 'application/json');
    }

    public function asJson(): self
    {
        return $this->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->withOption('body_format', 'json');
    }

    public function asForm(): self
    {
        return $this->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withOption('body_format', 'form');
    }

    public function withToken(string $token, string $type = 'Bearer'): self
    {
        return $this->withHeader('Authorization', trim($type . ' ' . $token));
    }

    public function withBasicAuth(string $username, string $password): self
    {
        return $this->withHeader('Authorization', 'Basic ' . base64_encode($username . ':' . $password));
    }

    /**
     * @param array<string,mixed> $query
     */
    public function withQuery(array $query): self
    {
        return $this->withOption('query', array_merge($this->options['query'] ?? [], $query));
    }

    public function timeout(int|float $seconds): self
    {
        return $this->withOption('timeout', $seconds);
    }

    public function connectTimeout(int|float $seconds): self
    {
        return $this->withOption('connect_timeout', $seconds);
    }

    public function retry(int $times, int $sleepMilliseconds = 0, ?callable $when = null): self
    {
        return $this->withOption('retry', [
            'times' => max(1, $times),
            'sleep' => max(0, $sleepMilliseconds),
            'when' => $when,
        ]);
    }

    /**
     * @param array<string,mixed> $query
     */
    public function get(string $url, array $query = []): ConnectResponse
    {
        return $this->send('GET', $url, ['query' => $query]);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function post(string $url, array $data = []): ConnectResponse
    {
        return $this->sendWithBody('POST', $url, $data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function put(string $url, array $data = []): ConnectResponse
    {
        return $this->sendWithBody('PUT', $url, $data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function patch(string $url, array $data = []): ConnectResponse
    {
        return $this->sendWithBody('PATCH', $url, $data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function delete(string $url, array $data = []): ConnectResponse
    {
        return $this->sendWithBody('DELETE', $url, $data);
    }

    /**
     * @param array<string,mixed> $extraOptions
     */
    public function send(string $method, string $url, array $extraOptions = []): ConnectResponse
    {
        $options = $this->buildOptions($extraOptions);
        $finalUrl = UrlBuilder::build(
            (string) ($this->connectionConfig['base_url'] ?? ''),
            $url,
            $options['query'] ?? [],
        );

        unset($options['query']);

        $retry = $options['retry'] ?? ['times' => 1, 'sleep' => 0, 'when' => null];
        unset($options['retry']);

        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $this->normalizeResponse(
                    $this->manager->driver($this->connection)->send(strtoupper($method), $finalUrl, $options)
                );

                if ($attempt >= $retry['times'] || !$this->shouldRetry($retry['when'], $response, null)) {
                    return $response;
                }
            } catch (ConnectionException $exception) {
                if ($attempt >= $retry['times'] || !$this->shouldRetry($retry['when'], null, $exception)) {
                    throw $exception;
                }
            }

            if ($retry['sleep'] > 0) {
                usleep($retry['sleep'] * 1000);
            }
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function sendWithBody(string $method, string $url, array $data): ConnectResponse
    {
        $format = $this->options['body_format'] ?? $this->connectionConfig['body_format'] ?? 'json';

        return match ($format) {
            'form' => $this->send($method, $url, ['form' => $data]),
            default => $this->asJson()->send($method, $url, ['json' => $data]),
        };
    }

    /**
     * @return array<string,string>
     */
    private function headers(): array
    {
        return Headers::normalize(array_merge(
            $this->connectionConfig['headers'] ?? [],
            $this->options['headers'] ?? [],
        ));
    }

    /**
     * @param array<string,mixed> $extraOptions
     * @return array<string,mixed>
     */
    private function buildOptions(array $extraOptions): array
    {
        $headers = $this->headers();
        $query = array_merge($this->options['query'] ?? [], $extraOptions['query'] ?? []);

        return array_merge(
            [
                'headers' => $headers,
                'timeout' => $this->connectionConfig['timeout'] ?? 10,
                'connect_timeout' => $this->connectionConfig['connect_timeout'] ?? 5,
                'query' => $query,
            ],
            $this->options,
            $extraOptions,
            [
                'headers' => $headers,
                'query' => $query,
            ],
        );
    }

    private function withOption(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->replaceOptions([$key => $value]);

        return $clone;
    }

    /**
     * @param array<string,mixed> $options
     */
    private function replaceOptions(array $options): void
    {
        $this->options = array_merge($this->options, $options);
    }

    private function normalizeResponse(ConnectResponseInterface $response): ConnectResponse
    {
        if ($response instanceof ConnectResponse) {
            return $response;
        }

        return new ConnectResponse($response->status(), $response->body(), $response->headers());
    }

    private function shouldRetry(?callable $when, ?ConnectResponse $response, ?\Throwable $exception): bool
    {
        if ($when === null) {
            return $exception !== null;
        }

        return (bool) $when($response, $exception);
    }
}
