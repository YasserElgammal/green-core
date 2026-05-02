<?php

namespace YasserElgammal\Green\Translation\Contracts;

/**
 * Abstraction for caching resolved translations.
 *
 * Implementations may store translations in memory (per-request),
 * on disk, in Redis, or any other medium.  The Translator checks
 * the cache before querying providers and stores results after
 * resolution to avoid redundant lookups.
 */
interface TranslationCacheInterface
{
    /**
     * Retrieve a cached translation.
     *
     * @return string|array<string,mixed>|null  null on cache miss.
     */
    public function get(string $key, string $locale): string|array|null;

    /**
     * Store a resolved translation.
     *
     * @param string|array<string,mixed> $value
     * @param int                        $ttl   Time-to-live in seconds.
     */
    public function set(string $key, string $locale, string|array $value, int $ttl = 3600): void;

    /**
     * Check whether a cached entry exists (and is still valid).
     */
    public function has(string $key, string $locale): bool;

    /**
     * Remove cached entries.
     *
     * @param string|null $locale  Flush only this locale, or all if null.
     */
    public function flush(?string $locale = null): void;

    /**
     * Retrieve all cached translations for a locale.
     *
     * @return array<string, string|array<string,mixed>>|null  null on cache miss.
     */
    public function getMany(string $locale): ?array;

    /**
     * Bulk-store translations for a locale.
     *
     * @param array<string, string|array<string,mixed>> $translations
     * @param int                                        $ttl
     */
    public function setMany(string $locale, array $translations, int $ttl = 3600): void;
}
