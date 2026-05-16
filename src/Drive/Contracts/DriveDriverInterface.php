<?php

namespace YasserElgammal\Green\Drive\Contracts;

use YasserElgammal\Green\Drive\Exceptions\FileNotFoundException;
use YasserElgammal\Green\Drive\Exceptions\InvalidPathException;
use YasserElgammal\Green\Drive\Exceptions\WriteFailedException;

/**
 * Contract for all Drive storage drivers.
 *
 * Every driver (local, S3, GCS, etc.) must implement this interface.
 * Paths passed to these methods are always relative to the driver's
 * configured root directory.
 */
interface DriveDriverInterface
{
    /**
     * Write content to a file, creating parent directories as needed.
     *
     * @param  string $path    Relative file path
     * @param  mixed  $content String content or a stream resource
     * @return bool
     *
     * @throws InvalidPathException   If the path fails security validation
     * @throws WriteFailedException   If the write operation fails
     */
    public function put(string $path, mixed $content): bool;

    /**
     * Read the entire contents of a file.
     *
     * @param  string $path  Relative file path
     * @return string
     *
     * @throws InvalidPathException    If the path fails security validation
     * @throws FileNotFoundException   If the file does not exist
     */
    public function get(string $path): string;

    /**
     * Delete a file.
     *
     * @param  string $path  Relative file path
     * @return bool
     *
     * @throws InvalidPathException   If the path fails security validation
     * @throws WriteFailedException   If the delete operation fails
     */
    public function delete(string $path): bool;

    /**
     * Check whether a file exists.
     *
     * @param  string $path  Relative file path
     * @return bool
     *
     * @throws InvalidPathException  If the path fails security validation
     */
    public function exists(string $path): bool;

    /**
     * Get the file size in bytes.
     *
     * @param  string $path  Relative file path
     * @return int
     *
     * @throws InvalidPathException    If the path fails security validation
     * @throws FileNotFoundException   If the file does not exist
     */
    public function size(string $path): int;

    /**
     * Get the file's last modification time as a Unix timestamp.
     *
     * @param  string $path  Relative file path
     * @return int
     *
     * @throws InvalidPathException    If the path fails security validation
     * @throws FileNotFoundException   If the file does not exist
     */
    public function lastModified(string $path): int;

    /**
     * Copy a file to a new location within the same disk.
     *
     * @param  string $from  Source relative path
     * @param  string $to    Destination relative path
     * @return bool
     *
     * @throws InvalidPathException    If either path fails security validation
     * @throws FileNotFoundException   If the source file does not exist
     * @throws WriteFailedException    If the copy operation fails
     */
    public function copy(string $from, string $to): bool;

    /**
     * Move (rename) a file to a new location within the same disk.
     *
     * @param  string $from  Source relative path
     * @param  string $to    Destination relative path
     * @return bool
     *
     * @throws InvalidPathException    If either path fails security validation
     * @throws FileNotFoundException   If the source file does not exist
     * @throws WriteFailedException    If the move operation fails
     */
    public function move(string $from, string $to): bool;

    /**
     * List all files in a directory (non-recursive).
     *
     * @param  string $directory  Relative directory path (empty = root)
     * @return string[]           Array of relative file paths
     *
     * @throws InvalidPathException  If the path fails security validation
     */
    public function files(string $directory = ''): array;

    /**
     * List all subdirectories in a directory (non-recursive).
     *
     * @param  string $directory  Relative directory path (empty = root)
     * @return string[]           Array of relative directory paths
     *
     * @throws InvalidPathException  If the path fails security validation
     */
    public function directories(string $directory = ''): array;

    /**
     * Create a directory, including any nested parent directories.
     *
     * @param  string $path  Relative directory path
     * @return bool
     *
     * @throws InvalidPathException   If the path fails security validation
     * @throws WriteFailedException   If directory creation fails
     */
    public function makeDirectory(string $path): bool;

    /**
     * Recursively delete a directory and all its contents.
     *
     * @param  string $path  Relative directory path
     * @return bool
     *
     * @throws InvalidPathException   If the path fails security validation
     * @throws WriteFailedException   If the delete operation fails
     */
    public function deleteDirectory(string $path): bool;

    /**
     * Open a read stream for a file.
     *
     * Returns a PHP stream resource for memory-efficient reading of large files.
     *
     * @param  string $path  Relative file path
     * @return resource
     *
     * @throws InvalidPathException    If the path fails security validation
     * @throws FileNotFoundException   If the file does not exist
     */
    public function readStream(string $path);

    /**
     * Write a file from a stream resource.
     *
     * Accepts a PHP stream resource for memory-efficient writing of large files.
     *
     * @param  string   $path      Relative file path
     * @param  resource $resource  Readable stream resource
     * @return bool
     *
     * @throws InvalidPathException   If the path fails security validation
     * @throws WriteFailedException   If the write operation fails
     */
    public function writeStream(string $path, $resource): bool;
}
