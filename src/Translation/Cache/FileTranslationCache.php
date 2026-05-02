<?php

namespace YasserElgammal\Green\Translation\Cache;

use YasserElgammal\Green\Translation\Contracts\TranslationCacheInterface;

/**
 * File-based translation cache for cross-request persistence.
 *
 * Serializes resolved translations to PHP files on disk so they
 * survive across requests without hitting providers every time.
 *
 * Cache files are stored as:
 *   {cachePath}/{locale}.php  →  returns ['key' => 'value', ...]
 *
 * Each file includes a TTL timestamp.  When the file is older
 * than the TTL, it is treated as a miss and regenerated.
 */
final class FileTranslationCache implements TranslationCacheInterface
{
    /**
     * In-memory mirror of loaded cache files (avoids re-reading disk).
     *
     * @var array<string, array<string, string|array<string,mixed>>|null>
     */
    private array $memory = [];

    /**
     * @param string $cachePath Absolute path to the cache directory.
     * @param int    $defaultTtl Default TTL in seconds.
     */
    public function __construct(
        private readonly string $cachePath,
        private readonly int $defaultTtl = 3600,
    ) {}

    /** @inheritDoc */
    public function get(string $key, string $locale): string|array|null
    {
        $data = $this->loadLocaleCache($locale);

        if ($data === null) {
            return null;
        }

        return $data[$key] ?? null;
    }

    /** @inheritDoc */
    public function set(string $key, string $locale, string|array $value, int $ttl = 3600): void
    {
        $data = $this->loadLocaleCache($locale) ?? [];
        $data[$key] = $value;

        $this->memory[$locale] = $data;
        $this->writeToDisk($locale, $data, $ttl);
    }

    /** @inheritDoc */
    public function has(string $key, string $locale): bool
    {
        $data = $this->loadLocaleCache($locale);

        return $data !== null && array_key_exists($key, $data);
    }

    /** @inheritDoc */
    public function flush(?string $locale = null): void
    {
        if ($locale !== null) {
            unset($this->memory[$locale]);
            $this->deleteFile($locale);
        } else {
            $this->memory = [];
            $this->deleteAllFiles();
        }
    }

    /** @inheritDoc */
    public function getMany(string $locale): ?array
    {
        return $this->loadLocaleCache($locale);
    }

    /** @inheritDoc */
    public function setMany(string $locale, array $translations, int $ttl = 3600): void
    {
        $existing = $this->loadLocaleCache($locale) ?? [];
        $merged   = array_merge($existing, $translations);

        $this->memory[$locale] = $merged;
        $this->writeToDisk($locale, $merged, $ttl);
    }

    /**
     * Load the cache file for a locale, respecting TTL.
     *
     * @return array<string, string|array<string,mixed>>|null
     */
    private function loadLocaleCache(string $locale): ?array
    {
        if (array_key_exists($locale, $this->memory)) {
            return $this->memory[$locale];
        }

        $filePath = $this->getFilePath($locale);

        if (!is_file($filePath)) {
            $this->memory[$locale] = null;
            return null;
        }

        $cached = require $filePath;

        if (!is_array($cached)) {
            $this->memory[$locale] = null;
            return null;
        }

        // Check TTL expiration.
        $expiresAt = $cached['__expires_at'] ?? 0;

        if (time() > $expiresAt) {
            $this->memory[$locale] = null;
            @unlink($filePath);
            return null;
        }

        unset($cached['__expires_at']);

        $this->memory[$locale] = $cached;

        return $cached;
    }

    /**
     * Write the cache to a PHP file on disk.
     *
     * @param array<string, string|array<string,mixed>> $data
     */
    private function writeToDisk(string $locale, array $data, int $ttl): void
    {
        $this->ensureCacheDirectory();

        $data['__expires_at'] = time() + ($ttl > 0 ? $ttl : $this->defaultTtl);

        $content  = "<?php\n\n// Auto-generated translation cache — do not edit.\n// Generated: "
                  . date('Y-m-d H:i:s') . "\n\nreturn "
                  . var_export($data, true) . ";\n";

        $filePath = $this->getFilePath($locale);
        $tempPath = $filePath . '.tmp.' . getmypid();

        // Atomic write: write to temp file, then rename.
        if (file_put_contents($tempPath, $content, LOCK_EX) !== false) {
            rename($tempPath, $filePath);
        }
    }

    /**
     * Get the cache file path for a locale.
     */
    private function getFilePath(string $locale): string
    {
        // Sanitize locale to prevent directory traversal.
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $locale);
        return $this->cachePath . DIRECTORY_SEPARATOR . $safe . '.php';
    }

    /**
     * Ensure the cache directory exists.
     */
    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    /**
     * Delete the cache file for a single locale.
     */
    private function deleteFile(string $locale): void
    {
        $filePath = $this->getFilePath($locale);

        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    /**
     * Delete all cache files.
     */
    private function deleteAllFiles(): void
    {
        if (!is_dir($this->cachePath)) {
            return;
        }

        $files = glob($this->cachePath . DIRECTORY_SEPARATOR . '*.php');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            @unlink($file);
        }
    }
}
