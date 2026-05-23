<?php

namespace YasserElgammal\Green\Connect\Drivers;

use YasserElgammal\Green\Connect\ConnectResponse;
use YasserElgammal\Green\Connect\Contracts\ConnectDriverInterface;
use YasserElgammal\Green\Connect\Exceptions\ConnectionException;
use YasserElgammal\Green\Connect\Exceptions\TimeoutException;
use YasserElgammal\Green\Connect\Support\Headers;

final class CurlDriver implements ConnectDriverInterface
{
    public function send(string $method, string $url, array $options = []): ConnectResponse
    {
        if (!extension_loaded('curl')) {
            throw new ConnectionException('The PHP cURL extension is required to use the Connect curl driver.');
        }

        $handle = curl_init($url);

        if ($handle === false) {
            throw new ConnectionException('Unable to initialize cURL.');
        }

        $responseHeaders = [];
        $headers = Headers::normalize($options['headers'] ?? []);

        $curlOptions = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => Headers::toCurlHeaders($headers),
            CURLOPT_TIMEOUT => (float) ($options['timeout'] ?? 10),
            CURLOPT_CONNECTTIMEOUT => (float) ($options['connect_timeout'] ?? 5),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $line = trim($line);

                if ($line === '' || !str_contains($line, ':')) {
                    return $length;
                }

                [$name, $value] = explode(':', $line, 2);
                $responseHeaders[trim($name)] = trim($value);

                return $length;
            },
        ];

        if (array_key_exists('json', $options)) {
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($options['json']);
        } elseif (array_key_exists('form', $options)) {
            $curlOptions[CURLOPT_POSTFIELDS] = http_build_query($options['form']);
        } elseif (array_key_exists('body', $options)) {
            $curlOptions[CURLOPT_POSTFIELDS] = $options['body'];
        }

        curl_setopt_array($handle, $curlOptions);

        try {
            $body = curl_exec($handle);
            $errno = curl_errno($handle);
            $error = curl_error($handle);
            $info = curl_getinfo($handle);
        } finally {
            curl_close($handle);
        }

        if ($body === false || $errno !== 0) {
            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                throw new TimeoutException($error !== '' ? $error : 'The outgoing HTTP request timed out.');
            }

            throw new ConnectionException($error !== '' ? $error : 'The outgoing HTTP request failed.');
        }

        return new ConnectResponse((int) ($info['http_code'] ?? 0), (string) $body, $responseHeaders, $info);
    }
}
