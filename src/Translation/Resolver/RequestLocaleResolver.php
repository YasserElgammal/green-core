<?php

namespace YasserElgammal\Green\Translation\Resolver;

use YasserElgammal\Green\Translation\Contracts\LocaleResolverInterface;

/**
 * Resolves locale from the HTTP request context.
 *
 * Checks the following sources in order:
 *   1. A query-string parameter (e.g. ?lang=ar)
 *   2. The Accept-Language HTTP header
 *
 * Returns null when no HTTP context is available (e.g. in CLI).
 */
final class RequestLocaleResolver implements LocaleResolverInterface
{
    /**
     * @param string        $queryParam     Query-string parameter name.
     * @param string[]|null $allowedLocales If set, only these locale codes are accepted.
     */
    public function __construct(
        private readonly string $queryParam = 'lang',
        private readonly ?array $allowedLocales = null,
    ) {}

    /** @inheritDoc */
    public function resolve(): ?string
    {
        // 1. Query-string parameter.
        $fromQuery = $_GET[$this->queryParam] ?? null;

        if (is_string($fromQuery) && $fromQuery !== '') {
            $locale = $this->sanitize($fromQuery);
            if ($locale !== null && $this->isAllowed($locale)) {
                return $locale;
            }
        }

        // 2. Accept-Language header.
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null;

        if (is_string($header) && $header !== '') {
            return $this->parseAcceptLanguage($header);
        }

        return null;
    }

    /**
     * Parse the Accept-Language header and return the best match.
     *
     * Example header: "ar-EG,ar;q=0.9,en-US;q=0.8,en;q=0.7"
     * Returns: "ar_EG"  (normalized with underscore)
     */
    private function parseAcceptLanguage(string $header): ?string
    {
        $locales = [];

        foreach (explode(',', $header) as $part) {
            $parts = explode(';', trim($part));
            $code  = trim($parts[0]);

            $quality = 1.0;
            if (isset($parts[1])) {
                $qPart = trim($parts[1]);
                if (str_starts_with($qPart, 'q=')) {
                    $quality = (float) substr($qPart, 2);
                }
            }

            $normalized = str_replace('-', '_', $code);
            $locales[$normalized] = $quality;
        }

        // Sort by quality descending.
        arsort($locales);

        foreach (array_keys($locales) as $locale) {
            $sanitized = $this->sanitize($locale);
            if ($sanitized !== null && $this->isAllowed($sanitized)) {
                return $sanitized;
            }
        }

        return null;
    }

    /**
     * Sanitize a locale code to prevent injection.
     * Allows only alphanumeric, underscore, and hyphen.
     */
    private function sanitize(string $locale): ?string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_\-]/', '', $locale);
        return ($clean !== '' && $clean !== null) ? $clean : null;
    }

    /**
     * Check against the allowed locale whitelist (if configured).
     */
    private function isAllowed(string $locale): bool
    {
        if ($this->allowedLocales === null) {
            return true;
        }

        return in_array($locale, $this->allowedLocales, true);
    }
}
