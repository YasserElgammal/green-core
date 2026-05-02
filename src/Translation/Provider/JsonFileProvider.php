<?php

namespace YasserElgammal\Green\Translation\Provider;

use YasserElgammal\Green\Translation\Contracts\TranslationProviderInterface;
use YasserElgammal\Green\Translation\Context\TranslationContext;

/**
 * Reads translations from JSON files on disk.
 *
 * Expected directory layout:
 *
 *   {basePath}/{locale}/{group}.json
 *
 *   lang/en/messages.json   → {"welcome": "Welcome, :name!"}
 *   lang/en/orders.json     → {"status": {"pending": "Pending"}}
 *   lang/ar/messages.json   → {"welcome": "أهلاً، :name!"}
 *
 * Keys use dot-notation:  "messages.welcome" → group "messages", path "welcome".
 * Context-aware: when a TranslationContext with a module is provided, the
 * provider first looks for a scoped file (e.g. "orders/checkout.json").
 */
final class JsonFileProvider implements TranslationProviderInterface
{
    /**
     * In-memory store of parsed JSON files, keyed by "{locale}.{group}".
     *
     * @var array<string, array<string, mixed>>
     */
    private array $loaded = [];

    /**
     * @param string $basePath Absolute path to the language directory (e.g. "/app/lang").
     */
    public function __construct(
        private readonly string $basePath,
    ) {}

    /** @inheritDoc */
    public function get(string $key, string $locale, ?TranslationContext $context = null): string|array|null
    {
        [$group, $path] = $this->parseKey($key, $context);

        $translations = $this->loadGroup($locale, $group);

        if ($translations === null) {
            return null;
        }

        return $this->resolveNestedKey($translations, $path);
    }

    /** @inheritDoc */
    public function has(string $key, string $locale, ?TranslationContext $context = null): bool
    {
        return $this->get($key, $locale, $context) !== null;
    }

    /** @inheritDoc */
    public function all(string $locale, ?TranslationContext $context = null): array
    {
        $localeDir = $this->basePath . DIRECTORY_SEPARATOR . $locale;

        if (!is_dir($localeDir)) {
            return [];
        }

        $results = [];
        $files   = glob($localeDir . DIRECTORY_SEPARATOR . '*.json');

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $group = pathinfo($file, PATHINFO_FILENAME);
            $data  = $this->loadGroup($locale, $group);

            if ($data !== null) {
                $flat = $this->flatten($data, $group);
                $results = array_merge($results, $flat);
            }
        }

        return $results;
    }

    /**
     * Split a dot-notation key into [group, remaining.path].
     *
     * "messages.welcome"       → ["messages", "welcome"]
     * "orders.status.pending"  → ["orders",   "status.pending"]
     *
     * If a context module is set and the key has no explicit group
     * (no dot), the module name is used as the group.
     *
     * @return array{0: string, 1: string}
     */
    private function parseKey(string $key, ?TranslationContext $context = null): array
    {
        $dot = strpos($key, '.');

        if ($dot === false) {
            // No group in the key — use context module or default "messages".
            $group = $context?->toGroup() ?? 'messages';
            return [$group, $key];
        }

        $group = substr($key, 0, $dot);
        $path  = substr($key, $dot + 1);

        return [$group, $path];
    }

    /**
     * Load and cache a single JSON file.
     *
     * @return array<string, mixed>|null
     */
    private function loadGroup(string $locale, string $group): ?array
    {
        $cacheKey = $locale . '.' . $group;

        if (array_key_exists($cacheKey, $this->loaded)) {
            return $this->loaded[$cacheKey];
        }

        $filePath = $this->basePath
            . DIRECTORY_SEPARATOR . $locale
            . DIRECTORY_SEPARATOR . $group . '.json';

        if (!is_file($filePath)) {
            $this->loaded[$cacheKey] = null;
            return null;
        }

        $contents = file_get_contents($filePath);

        if ($contents === false) {
            $this->loaded[$cacheKey] = null;
            return null;
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            $this->loaded[$cacheKey] = null;
            return null;
        }

        $this->loaded[$cacheKey] = $decoded;

        return $decoded;
    }

    /**
     * Walk a nested array by dot-separated path segments.
     *
     * "status.pending" on {"status": {"pending": "Pending"}} → "Pending"
     */
    private function resolveNestedKey(array $data, string $path): string|array|null
    {
        $segments = explode('.', $path);
        $current  = $data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return is_string($current) || is_array($current) ? $current : null;
    }

    /**
     * Flatten a nested array into dot-notation keys.
     *
     * @return array<string, string>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $fullKey = $prefix !== '' ? $prefix . '.' . $key : $key;

            if (is_array($value) && !$this->isPluralizationArray($value)) {
                $result = array_merge($result, $this->flatten($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Detect whether an array is a pluralization set (has CLDR keys).
     */
    private function isPluralizationArray(array $data): bool
    {
        $pluralKeys = ['zero', 'one', 'two', 'few', 'many', 'other'];

        foreach (array_keys($data) as $key) {
            if (in_array($key, $pluralKeys, true)) {
                return true;
            }
        }

        return false;
    }
}
