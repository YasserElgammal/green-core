<?php

namespace YasserElgammal\Green\Connect\Support;

final class UrlBuilder
{
    /**
     * @param array<string,mixed> $query
     */
    public static function build(string $baseUrl, string $url, array $query = []): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = trim($url);

        if ($url === '') {
            $url = '/';
        }

        $resolved = self::isAbsolute($url)
            ? $url
            : $baseUrl . '/' . ltrim($url, '/');

        if ($query === []) {
            return $resolved;
        }

        $separator = str_contains($resolved, '?') ? '&' : '?';

        return $resolved . $separator . http_build_query($query);
    }

    public static function isAbsolute(string $url): bool
    {
        return preg_match('#^https?://#i', $url) === 1;
    }
}
