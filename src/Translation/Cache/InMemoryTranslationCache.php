<?php

namespace YasserElgammal\Green\Translation\Cache;

use YasserElgammal\Green\Translation\Contracts\TranslationCacheInterface;

/**
 * Array-based in-memory cache for a single request lifecycle.
 *
 * Zero external dependencies — ideal for CLI commands, queue
 * workers, tests, and short-lived HTTP requests where cross-
 * request persistence is not needed.
 */
final class InMemoryTranslationCache implements TranslationCacheInterface
{
    /**
     * @var array<string, array<string, string|array<string,mixed>>>
     */
    private array $store = [];

    /** @inheritDoc */
    public function get(string $key, string $locale): string|array|null
    {
        return $this->store[$locale][$key] ?? null;
    }

    /** @inheritDoc */
    public function set(string $key, string $locale, string|array $value, int $ttl = 3600): void
    {
        // TTL is ignored for in-memory cache (evicted at end of request).
        $this->store[$locale][$key] = $value;
    }

    /** @inheritDoc */
    public function has(string $key, string $locale): bool
    {
        return isset($this->store[$locale][$key]);
    }

    /** @inheritDoc */
    public function flush(?string $locale = null): void
    {
        if ($locale !== null) {
            unset($this->store[$locale]);
        } else {
            $this->store = [];
        }
    }

    /** @inheritDoc */
    public function getMany(string $locale): ?array
    {
        return $this->store[$locale] ?? null;
    }

    /** @inheritDoc */
    public function setMany(string $locale, array $translations, int $ttl = 3600): void
    {
        $this->store[$locale] = array_merge(
            $this->store[$locale] ?? [],
            $translations,
        );
    }
}
