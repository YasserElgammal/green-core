<?php

namespace YasserElgammal\Green\Drive;

use YasserElgammal\Green\Drive\Contracts\DriveDriverInterface;
use YasserElgammal\Green\Drive\Drivers\FakeDriver;
use YasserElgammal\Green\Drive\Drivers\LocalDriver;
use YasserElgammal\Green\Drive\Exceptions\DiskNotFoundException;

/**
 * Central disk resolver and driver registry.
 *
 * Responsible for:
 *  - Reading disk configuration
 *  - Lazy-instantiating driver instances on first access
 *  - Caching resolved drivers for the request lifecycle
 *  - Supporting custom driver creators for extensibility (S3, GCS, etc.)
 *  - Swapping disks with FakeDriver for testing
 *
 * Follows the same manager pattern as LogManager.
 */
final class DriveManager
{
    /**
     * Resolved driver instances keyed by disk name.
     * @var array<string, DriveDriverInterface>
     */
    private array $resolvedDisks = [];

    /**
     * Custom driver creator callbacks.
     *
     * Registered via extend() to support third-party storage backends.
     * Key = driver type name (e.g. 's3'), value = factory callable.
     *
     * @var array<string, callable(array): DriveDriverInterface>
     */
    private array $customCreators = [];

    /**
     * @param array{
     *     default?: string,
     *     disks?: array<string, array{driver: string, root?: string, ...}>
     * } $config  Full drive configuration (typically from config/drive.php)
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * Get a driver instance for the given disk name.
     *
     * If no name is provided, the default disk from config is used.
     * Drivers are lazy-loaded and cached for the request lifecycle.
     *
     * @param  string|null $name  Disk name (null = default)
     * @return DriveDriverInterface
     *
     * @throws DiskNotFoundException  If the disk is not configured
     */
    public function disk(?string $name = null): DriveDriverInterface
    {
        $name = $name ?? $this->getDefaultDisk();

        if (!isset($this->resolvedDisks[$name])) {
            $this->resolvedDisks[$name] = $this->resolve($name);
        }

        return $this->resolvedDisks[$name];
    }

    /**
     * Get the default disk name from configuration.
     */
    public function getDefaultDisk(): string
    {
        return $this->config['default'] ?? 'local';
    }

    /**
     * Register a custom driver creator.
     *
     * Use this to add support for third-party storage backends:
     *
     *   $manager->extend('s3', function (array $config) {
     *       return new S3Driver($config);
     *   });
     *
     * @param string   $driver   Driver type name (e.g. 's3', 'gcs')
     * @param callable $creator  Factory: fn(array $config): DriveDriverInterface
     */
    public function extend(string $driver, callable $creator): void
    {
        $this->customCreators[$driver] = $creator;
    }

    /**
     * Swap a disk with a FakeDriver for testing.
     *
     * The FakeDriver replaces the resolved driver for the given disk,
     * and all subsequent calls to disk($name) return the fake.
     *
     * @param  string|null $disk  Disk to fake (null = default disk)
     * @return FakeDriver         The fake instance (for assertions)
     */
    public function fake(?string $disk = null): FakeDriver
    {
        $disk = $disk ?? $this->getDefaultDisk();

        $fake = new FakeDriver();
        $this->resolvedDisks[$disk] = $fake;

        return $fake;
    }

    /**
     * Get the full configuration array.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get all currently resolved disk names.
     *
     * @return string[]
     */
    public function getResolvedDisks(): array
    {
        return array_keys($this->resolvedDisks);
    }

    /**
     * Purge a resolved disk instance from the cache.
     *
     * The next call to disk($name) will re-resolve it from config.
     * Useful for testing or when config changes at runtime.
     *
     * @param string|null $name  Disk name (null = purge all)
     */
    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->resolvedDisks = [];
        } else {
            unset($this->resolvedDisks[$name]);
        }
    }

    // ------------------------------------------------------------------
    //  Driver resolution
    // ------------------------------------------------------------------

    /**
     * Resolve a disk name to a driver instance.
     *
     * Reads the disk configuration, determines the driver type,
     * and either uses a custom creator or built-in factory method.
     *
     * @throws DiskNotFoundException  If the disk is not configured
     * @throws \RuntimeException      If the driver type is unsupported
     */
    private function resolve(string $name): DriveDriverInterface
    {
        $diskConfig = $this->config['disks'][$name] ?? null;

        if ($diskConfig === null) {
            throw new DiskNotFoundException($name);
        }

        $driverType = $diskConfig['driver'] ?? 'local';

        // Check custom creators first
        if (isset($this->customCreators[$driverType])) {
            $driver = ($this->customCreators[$driverType])($diskConfig);

            if (!$driver instanceof DriveDriverInterface) {
                throw new \RuntimeException(
                    "Custom driver creator for '{$driverType}' must return a DriveDriverInterface instance."
                );
            }

            return $driver;
        }

        // Built-in driver factory
        return match ($driverType) {
            'local' => $this->createLocalDriver($diskConfig),
            default => throw new \RuntimeException(
                "Unsupported drive driver: '{$driverType}'. "
                . 'Register a custom creator via DriveManager::extend().'
            ),
        };
    }

    /**
     * Create a LocalDriver instance from config.
     */
    private function createLocalDriver(array $config): LocalDriver
    {
        return new LocalDriver($config);
    }
}
