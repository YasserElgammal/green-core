<?php

namespace YasserElgammal\Green\Connect\Drivers;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use YasserElgammal\Green\Connect\ConnectResponse;
use YasserElgammal\Green\Connect\Contracts\ConnectDriverInterface;
use YasserElgammal\Green\Connect\Exceptions\ConnectionException;
use YasserElgammal\Green\Connect\Exceptions\TimeoutException;
use YasserElgammal\Green\Connect\Support\Headers;

final class SymfonyHttpDriver implements ConnectDriverInterface
{
    public function __construct(private readonly ?HttpClientInterface $client = null)
    {
    }

    public function send(string $method, string $url, array $options = []): ConnectResponse
    {
        $client = $this->client ?? HttpClient::create([
            'max_redirects' => 0,
        ]);

        $requestOptions = [
            'headers' => Headers::normalize($options['headers'] ?? []),
            'timeout' => (float) ($options['timeout'] ?? 10),
            'max_redirects' => 0,
        ];

        if (array_key_exists('json', $options)) {
            $requestOptions['json'] = $options['json'];
        } elseif (array_key_exists('form', $options)) {
            $requestOptions['body'] = $options['form'];
        } elseif (array_key_exists('body', $options)) {
            $requestOptions['body'] = $options['body'];
        }

        try {
            $response = $client->request(strtoupper($method), $url, $requestOptions);

            return new ConnectResponse(
                $response->getStatusCode(),
                $response->getContent(false),
                $response->getHeaders(false),
                $response->getInfo(),
            );
        } catch (TransportExceptionInterface $exception) {
            if ($this->isTimeout($exception)) {
                throw new TimeoutException($exception->getMessage(), previous: $exception);
            }

            throw new ConnectionException($exception->getMessage(), previous: $exception);
        }
    }

    private function isTimeout(TransportExceptionInterface $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'timeout')
            || str_contains($message, 'timed out')
            || str_contains($message, 'operation timed out');
    }
}
