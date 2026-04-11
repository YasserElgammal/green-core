<?php

namespace YasserElgammal\Green\Database\Migrations;

use PDO;

/**
 * Persists migration run history to the `migrations` table.
 *
 * Schema:
 *   id         INT AUTO_INCREMENT PRIMARY KEY
 *   migration  VARCHAR(255) NOT NULL UNIQUE
 *   batch      INT NOT NULL
 *   ran_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 */
class MigrationRepository
{
    private const TABLE = 'migrations';

    public function __construct(private readonly PDO $pdo) {}

    // ─── Setup ─────────────────────────────────────────────────────────────

    /**
     * Create the migrations tracking table if it does not exist.
     */
    public function ensureTableExists(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `" . self::TABLE . "` (
                `id`        INT          NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(255) NOT NULL,
                `batch`     INT          NOT NULL,
                `ran_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    // ─── Queries ───────────────────────────────────────────────────────────

    /**
     * Check if a migration has already been run.
     */
    public function hasRun(string $migration): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `" . self::TABLE . "` WHERE `migration` = ?"
        );
        $stmt->execute([$migration]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Record a completed migration.
     */
    public function log(string $migration, int $batch): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO `" . self::TABLE . "` (`migration`, `batch`) VALUES (?, ?)"
        );
        $stmt->execute([$migration, $batch]);
    }

    /**
     * Remove a migration record (used on rollback).
     */
    public function delete(string $migration): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM `" . self::TABLE . "` WHERE `migration` = ?"
        );
        $stmt->execute([$migration]);
    }

    /**
     * Return the highest batch number (0 if no migrations run yet).
     */
    public function getLastBatch(): int
    {
        $result = $this->pdo
            ->query("SELECT MAX(`batch`) FROM `" . self::TABLE . "`")
            ->fetchColumn();

        return $result === null ? 0 : (int) $result;
    }

    /**
     * Return migration names belonging to a specific batch, newest first.
     *
     * @return string[]
     */
    public function getMigrationsByBatch(int $batch): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT `migration` FROM `" . self::TABLE . "` WHERE `batch` = ? ORDER BY `id` DESC"
        );
        $stmt->execute([$batch]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Return all run migration names.
     *
     * @return string[]
     */
    public function getAllRan(): array
    {
        return $this->pdo
            ->query("SELECT `migration` FROM `" . self::TABLE . "` ORDER BY `batch`, `id`")
            ->fetchAll(PDO::FETCH_COLUMN);
    }
}
