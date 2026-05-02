<?php

namespace YasserElgammal\Green\Translation\Provider;

use Doctrine\DBAL\Connection;
use YasserElgammal\Green\Translation\Contracts\TranslationProviderInterface;
use YasserElgammal\Green\Translation\Context\TranslationContext;

/**
 * Loads translations from a database table via Doctrine DBAL.
 *
 * Table schema (created by migration):
 *
 *   translations
 *   ├── id          INT AUTO_INCREMENT PRIMARY KEY
 *   ├── locale      VARCHAR(10)   NOT NULL
 *   ├── group       VARCHAR(100)  NOT NULL   (e.g. "messages", "orders")
 *   ├── key         VARCHAR(255)  NOT NULL
 *   ├── value       TEXT          NOT NULL
 *   ├── module      VARCHAR(100)  NULL       (context scoping)
 *   ├── created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
 *   └── updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE
 *
 * Ideal for admin-editable translations that override file-based defaults.
 */
final class DatabaseProvider implements TranslationProviderInterface
{
    private const TABLE = 'translations';

    /**
     * In-memory cache of loaded rows, keyed by "{locale}.{module|_}".
     *
     * @var array<string, array<string, string|array<string,mixed>>>
     */
    private array $loaded = [];

    public function __construct(
        private readonly Connection $connection,
    ) {}

    /** @inheritDoc */
    public function get(string $key, string $locale, ?TranslationContext $context = null): string|array|null
    {
        $this->loadForLocale($locale, $context);

        $cacheKey = $this->buildCacheKey($locale, $context);
        $fullKey  = $key;

        return $this->loaded[$cacheKey][$fullKey] ?? null;
    }

    /** @inheritDoc */
    public function has(string $key, string $locale, ?TranslationContext $context = null): bool
    {
        $this->loadForLocale($locale, $context);

        $cacheKey = $this->buildCacheKey($locale, $context);

        return isset($this->loaded[$cacheKey][$key]);
    }

    /** @inheritDoc */
    public function all(string $locale, ?TranslationContext $context = null): array
    {
        $this->loadForLocale($locale, $context);

        $cacheKey = $this->buildCacheKey($locale, $context);

        return $this->loaded[$cacheKey] ?? [];
    }

    /**
     * Bulk-load all rows for a locale (and optional module) into memory.
     */
    private function loadForLocale(string $locale, ?TranslationContext $context = null): void
    {
        $cacheKey = $this->buildCacheKey($locale, $context);

        if (array_key_exists($cacheKey, $this->loaded)) {
            return;
        }

        $qb = $this->connection->createQueryBuilder()
            ->select('`group`', '`key`', '`value`')
            ->from(self::TABLE)
            ->where('`locale` = :locale')
            ->setParameter('locale', $locale);

        if ($context !== null && $context->module !== null) {
            $qb->andWhere('(`module` = :module OR `module` IS NULL)')
               ->setParameter('module', $context->module);
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();

        $translations = [];

        foreach ($rows as $row) {
            $fullKey = $row['group'] . '.' . $row['key'];
            $value   = $row['value'];

            // Attempt to decode JSON values (for plural arrays stored as JSON).
            $decoded = json_decode($value, true);
            $translations[$fullKey] = is_array($decoded) ? $decoded : $value;
        }

        $this->loaded[$cacheKey] = $translations;
    }

    /**
     * Build a unique cache key for the in-memory store.
     */
    private function buildCacheKey(string $locale, ?TranslationContext $context = null): string
    {
        $module = $context?->module ?? '_global';
        return $locale . '.' . $module;
    }
}
