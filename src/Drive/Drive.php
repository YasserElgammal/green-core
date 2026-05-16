<?php

namespace YasserElgammal\Green\Drive;

use YasserElgammal\Green\Drive\Contracts\DriveDriverInterface;
use YasserElgammal\Green\Drive\Drivers\FakeDriver;

/**
 * Primary injectable service for file storage operations.
 *
 * This is the class application code interacts with.  It provides
 * a clean, consistent API that delegates all operations to the
 * configured driver via DriveManager.
 *
 * Usage:
 *
 *   // Inject into your service / controller
 *   public function __construct(private readonly Drive $drive) {}
 *
 *   // Store a file on the default disk
 *   $this->drive->put('users/1/avatar.png', $imageContent);
 *
 *   // Read it back
 *   $content = $this->drive->get('users/1/avatar.png');
 *
 *   // Use a specific disk
 *   $this->drive->disk('public')->put('uploads/photo.jpg', $data);
 *
 *   // Testing
 *   $fake = $this->drive->fake();
 *   $this->drive->put('test.txt', 'hello');
 *   $fake->assertExists('test.txt');
 */
final class Drive
{
    public function __construct(private readonly DriveManager $manager)
    {
    }

    // ------------------------------------------------------------------
    //  Core file operations (delegate to default disk)
    // ------------------------------------------------------------------

    /**
     * Write content to a file on the default disk.
     *
     * Creates parent directories automatically.
     *
     * @param  string $path    Relative file path
     * @param  mixed  $content String content or stream resource
     * @return bool
     */
    public function put(string $path, mixed $content): bool
    {
        return $this->manager->disk()->put($path, $content);
    }

    /**
     * Read the entire contents of a file from the default disk.
     *
     * @param  string $path  Relative file path
     * @return string
     */
    public function get(string $path): string
    {
        return $this->manager->disk()->get($path);
    }

    /**
     * Delete a file from the default disk.
     *
     * @param  string $path  Relative file path
     * @return bool
     */
    public function delete(string $path): bool
    {
        return $this->manager->disk()->delete($path);
    }

    /**
     * Check whether a file exists on the default disk.
     *
     * @param  string $path  Relative file path
     * @return bool
     */
    public function exists(string $path): bool
    {
        return $this->manager->disk()->exists($path);
    }

    /**
     * Get the file size in bytes from the default disk.
     *
     * @param  string $path  Relative file path
     * @return int
     */
    public function size(string $path): int
    {
        return $this->manager->disk()->size($path);
    }

    /**
     * Get the file's last modification time as a Unix timestamp.
     *
     * @param  string $path  Relative file path
     * @return int
     */
    public function lastModified(string $path): int
    {
        return $this->manager->disk()->lastModified($path);
    }

    /**
     * Copy a file to a new location on the default disk.
     *
     * @param  string $from  Source path
     * @param  string $to    Destination path
     * @return bool
     */
    public function copy(string $from, string $to): bool
    {
        return $this->manager->disk()->copy($from, $to);
    }

    /**
     * Move (rename) a file on the default disk.
     *
     * @param  string $from  Source path
     * @param  string $to    Destination path
     * @return bool
     */
    public function move(string $from, string $to): bool
    {
        return $this->manager->disk()->move($from, $to);
    }

    // ------------------------------------------------------------------
    //  Directory operations
    // ------------------------------------------------------------------

    /**
     * List all files in a directory on the default disk.
     *
     * @param  string $directory  Relative directory path
     * @return string[]
     */
    public function files(string $directory = ''): array
    {
        return $this->manager->disk()->files($directory);
    }

    /**
     * List all subdirectories in a directory on the default disk.
     *
     * @param  string $directory  Relative directory path
     * @return string[]
     */
    public function directories(string $directory = ''): array
    {
        return $this->manager->disk()->directories($directory);
    }

    /**
     * Create a directory on the default disk.
     *
     * @param  string $path  Relative directory path
     * @return bool
     */
    public function makeDirectory(string $path): bool
    {
        return $this->manager->disk()->makeDirectory($path);
    }

    /**
     * Recursively delete a directory on the default disk.
     *
     * @param  string $path  Relative directory path
     * @return bool
     */
    public function deleteDirectory(string $path): bool
    {
        return $this->manager->disk()->deleteDirectory($path);
    }

    // ------------------------------------------------------------------
    //  Streaming
    // ------------------------------------------------------------------

    /**
     * Open a read stream for a file on the default disk.
     *
     * @param  string $path  Relative file path
     * @return resource
     */
    public function readStream(string $path)
    {
        return $this->manager->disk()->readStream($path);
    }

    /**
     * Write a file from a stream resource on the default disk.
     *
     * @param  string   $path      Relative file path
     * @param  resource $resource  Readable stream
     * @return bool
     */
    public function writeStream(string $path, $resource): bool
    {
        return $this->manager->disk()->writeStream($path, $resource);
    }

    // ------------------------------------------------------------------
    //  Disk switching
    // ------------------------------------------------------------------

    /**
     * Get a driver instance for a specific disk.
     *
     * @param  string $name  Disk name from config/drive.php
     * @return DriveDriverInterface
     */
    public function disk(string $name): DriveDriverInterface
    {
        return $this->manager->disk($name);
    }

    // ------------------------------------------------------------------
    //  Testing
    // ------------------------------------------------------------------

    /**
     * Swap a disk with a FakeDriver for testing.
     *
     * Returns the FakeDriver instance for making assertions.
     *
     * @param  string|null $disk  Disk to fake (null = default)
     * @return FakeDriver
     */
    public function fake(?string $disk = null): FakeDriver
    {
        return $this->manager->fake($disk);
    }

    // ------------------------------------------------------------------
    //  Manager access
    // ------------------------------------------------------------------

    /**
     * Get the underlying DriveManager instance.
     *
     * Useful for registering custom drivers or advanced configuration.
     */
    public function getManager(): DriveManager
    {
        return $this->manager;
    }
}
