<?php

namespace YasserElgammal\Green\Logging;

use YasserElgammal\Green\ErrorHandling\ErrorRecord;

/**
 * Central driver registry and dispatcher for the logging system.
 *
 * Manages multiple LoggerInterface drivers, dispatches error records to
 * all eligible drivers, and enforces safety mechanisms:
 *
 * - **Deduplication**: same fingerprint logged at most $maxDuplicates times per request
 * - **Rate limiting**: same fingerprint logged at most $rateLimit times per time window
 * - **Driver isolation**: a failing driver never prevents other drivers from executing
 */
final class LogManager
{
    /** @var LoggerInterface[] */
    private array $drivers = [];

    /**
     * Per-request dedup counter.
     * @var array<string, int>  fingerprint => count
     */
    private array $logged = [];

    /**
     * File-based rate limit state.
     * @var array<string, array{count: int, window_start: int}>
     */
    private array $rateLimitCache = [];

    /** Max times the same fingerprint can be logged in one request lifecycle. */
    private int $maxDuplicates = 5;

    /** Max logs per fingerprint per rate-limit window. */
    private int $rateLimit = 50;

    /** Rate-limit window in seconds. */
    private int $rateWindow = 60;

    /** Directory for rate-limit state files (null = rate limiting disabled). */
    private ?string $rateLimitDir = null;

    /**
     * Register a logging driver.
     */
    public function addDriver(LoggerInterface $driver): void
    {
        $this->drivers[] = $driver;
    }

    /**
     * Get all registered drivers.
     *
     * @return LoggerInterface[]
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * Configure per-request deduplication limit.
     *
     * @param int $max  Maximum times the same error can be logged per request (0 = unlimited)
     */
    public function setMaxDuplicates(int $max): void
    {
        $this->maxDuplicates = $max;
    }

    /**
     * Configure file-based rate limiting.
     *
     * @param int    $limit         Max logs per fingerprint per window
     * @param int    $windowSeconds Window size in seconds
     * @param string $stateDir      Directory to store rate-limit counters
     */
    public function setRateLimit(int $limit, int $windowSeconds, string $stateDir): void
    {
        $this->rateLimit    = $limit;
        $this->rateWindow   = $windowSeconds;
        $this->rateLimitDir = $stateDir;
    }

    /**
     * Dispatch an ErrorRecord to all eligible drivers.
     *
     * Applies deduplication and rate limiting before dispatching.
     * Each driver call is isolated — a failing driver never breaks the app
     * or prevents other drivers from executing.
     */
    public function log(ErrorRecord $record): void
    {
        $fingerprint = $record->fingerprint;

        // --- Deduplication: same error already logged N times this request? ---
        if ($this->maxDuplicates > 0) {
            $count = $this->logged[$fingerprint] ?? 0;
            if ($count >= $this->maxDuplicates) {
                return; // Skip — already logged enough times
            }
            $this->logged[$fingerprint] = $count + 1;
        }

        // --- Rate limiting: check file-based counter ---
        if ($this->rateLimitDir !== null && $this->isRateLimited($fingerprint)) {
            return;
        }

        // --- Dispatch to all eligible drivers ---
        foreach ($this->drivers as $driver) {
            try {
                if ($driver->supports($record->level)) {
                    $driver->log($record);
                }
            } catch (\Throwable $e) {
                // Driver failure must NEVER break the application.
                // Last-resort fallback: write to PHP's built-in error log.
                error_log(sprintf(
                    '[Green LogManager] Driver %s failed: %s in %s:%d',
                    get_class($driver),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ));
            }
        }
    }

    /**
     * Check if a fingerprint has exceeded its rate limit.
     *
     * Uses a simple file-based counter per fingerprint. The file stores
     * JSON with {count, window_start}. When the window expires, the
     * counter resets.
     */
    private function isRateLimited(string $fingerprint): bool
    {
        try {
            // Ensure state directory exists
            if (!is_dir($this->rateLimitDir)) {
                mkdir($this->rateLimitDir, 0755, true);
            }

            $file = $this->rateLimitDir . '/' . $fingerprint . '.json';
            $now  = time();

            // Read existing state
            $state = null;
            if (file_exists($file)) {
                $raw = file_get_contents($file);
                if ($raw !== false) {
                    $state = json_decode($raw, true);
                }
            }

            // Reset if window expired or no state
            if ($state === null || ($now - ($state['window_start'] ?? 0)) >= $this->rateWindow) {
                $state = ['count' => 0, 'window_start' => $now];
            }

            // Check limit
            if ($state['count'] >= $this->rateLimit) {
                return true; // Rate limited
            }

            // Increment and save
            $state['count']++;
            file_put_contents($file, json_encode($state), LOCK_EX);

            return false;
        } catch (\Throwable) {
            // If rate-limit checking itself fails, allow the log through
            return false;
        }
    }
}
