<?php

namespace YasserElgammal\Green\ErrorHandling;

/**
 * Collects and sanitizes request-level context for error records.
 *
 * Automatically attaches URL, HTTP method, client IP, and safe-filtered
 * headers to every error that occurs during a web request. Sensitive
 * headers (Authorization, Cookie, tokens) are stripped.
 */
final class RequestContext
{
    /**
     * Headers that must NEVER appear in logs.
     */
    private const SENSITIVE_HEADERS = [
        'HTTP_AUTHORIZATION',
        'HTTP_COOKIE',
        'HTTP_SET_COOKIE',
        'HTTP_PROXY_AUTHORIZATION',
    ];

    /**
     * Patterns (case-insensitive) — any header key matching these is stripped.
     */
    private const SENSITIVE_PATTERNS = [
        'token',
        'secret',
        'password',
        'key',
        'credential',
    ];

    /**
     * Capture the current request context as an associative array.
     *
     * Safe to call from any context — returns an empty subset if no
     * request data is available (e.g. CLI).
     *
     * @return array{url: string, method: string, ip: string, headers: array, user_id: mixed, timestamp: string}
     */
    public static function capture(): array
    {
        $server = $_SERVER ?? [];
        $isCli  = (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server');

        return [
            'url'       => $isCli ? 'cli' : self::buildUrl($server),
            'method'    => $server['REQUEST_METHOD'] ?? ($isCli ? 'CLI' : 'UNKNOWN'),
            'ip'        => self::getClientIp($server),
            'headers'   => $isCli ? [] : self::filterHeaders($server),
            'user_id'   => self::resolveUserId(),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Build the full request URL from $_SERVER.
     */
    private static function buildUrl(array $server): string
    {
        $scheme = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? 'unknown';
        $uri    = $server['REQUEST_URI'] ?? '/';

        return "{$scheme}://{$host}{$uri}";
    }

    /**
     * Best-effort client IP resolution (respects X-Forwarded-For).
     */
    private static function getClientIp(array $server): string
    {
        return $server['HTTP_X_FORWARDED_FOR']
            ?? $server['HTTP_CLIENT_IP']
            ?? $server['REMOTE_ADDR']
            ?? 'unknown';
    }

    /**
     * Extract HTTP headers from $_SERVER and strip sensitive ones.
     *
     * @return array<string, string> Header name => value (safe only)
     */
    private static function filterHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            // PHP stores HTTP headers as HTTP_* in $_SERVER
            if (!str_starts_with($key, 'HTTP_')) {
                continue;
            }

            // Skip explicitly blacklisted headers
            if (in_array($key, self::SENSITIVE_HEADERS, true)) {
                continue;
            }

            // Skip headers matching sensitive patterns
            $keyLower = strtolower($key);
            $isSensitive = false;
            foreach (self::SENSITIVE_PATTERNS as $pattern) {
                if (str_contains($keyLower, $pattern)) {
                    $isSensitive = true;
                    break;
                }
            }
            if ($isSensitive) {
                continue;
            }

            // Convert HTTP_ACCEPT_LANGUAGE → Accept-Language
            $name = str_replace('_', '-', substr($key, 5));
            $name = ucwords(strtolower($name), '-');
            $headers[$name] = $value;
        }

        return $headers;
    }

    /**
     * Attempt to resolve the current user ID from the session.
     *
     * Returns null if no session or user is available.
     */
    private static function resolveUserId(): mixed
    {
        // Only attempt if a session is active (avoid starting one just for logging)
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        return $_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? null;
    }
}
