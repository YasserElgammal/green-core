<?php

namespace YasserElgammal\Green\Connect\Support;

final class Headers
{
    /**
     * @param array<string,string|string[]> $headers
     * @return array<string,string>
     */
    public static function normalize(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $canonical = self::canonicalName((string) $name);
            $normalized[$canonical] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return $normalized;
    }

    /**
     * @param array<string,string> $headers
     */
    public static function get(array $headers, string $name, ?string $default = null): ?string
    {
        $wanted = strtolower($name);

        foreach ($headers as $headerName => $value) {
            if (strtolower($headerName) === $wanted) {
                return $value;
            }
        }

        return $default;
    }

    public static function canonicalName(string $name): string
    {
        $parts = explode('-', strtolower(str_replace('_', '-', trim($name))));

        return implode('-', array_map(static fn(string $part) => ucfirst($part), $parts));
    }

    /**
     * @param array<string,string> $headers
     * @return string[]
     */
    public static function toCurlHeaders(array $headers): array
    {
        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return $lines;
    }
}
