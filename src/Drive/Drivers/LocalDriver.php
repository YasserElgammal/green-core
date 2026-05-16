<?php

namespace YasserElgammal\Green\Drive\Drivers;

use YasserElgammal\Green\Drive\Contracts\DriveDriverInterface;
use YasserElgammal\Green\Drive\PathValidator;
use YasserElgammal\Green\Drive\Exceptions\FileNotFoundException;
use YasserElgammal\Green\Drive\Exceptions\InvalidPathException;
use YasserElgammal\Green\Drive\Exceptions\WriteFailedException;

/**
 * Local filesystem storage driver.
 *
 * Stores files on the server's local disk using PHP's native
 * filesystem functions.  All paths are validated through
 * PathValidator before any I/O operation.
 *
 * Features:
 *  - Automatic parent directory creation
 *  - Atomic writes via LOCK_EX
 *  - Stream support for large files
 *  - Configurable file/directory permissions
 */
final class LocalDriver implements DriveDriverInterface
{
    private readonly PathValidator $pathValidator;

    /** Default permission for created files. */
    private readonly int $filePermission;

    /** Default permission for created directories. */
    private readonly int $dirPermission;

    /**
     * @param array{
     *     root: string,
     *     permissions?: array{
     *         file?: array{public?: int, private?: int},
     *         dir?: array{public?: int, private?: int}
     *     }
     * } $config
     */
    public function __construct(private readonly array $config)
    {
        if (!isset($config['root']) || $config['root'] === '') {
            throw new \InvalidArgumentException('LocalDriver requires a non-empty "root" configuration value.');
        }

        $this->pathValidator  = new PathValidator($config['root']);
        $this->filePermission = $config['permissions']['file']['public'] ?? 0644;
        $this->dirPermission  = $config['permissions']['dir']['public']  ?? 0755;
    }

    // ------------------------------------------------------------------
    //  Core file operations
    // ------------------------------------------------------------------

    /**
     * {@inheritDoc}
     */
    public function put(string $path, mixed $content): bool
    {
        $fullPath = $this->pathValidator->resolve($path);
        $this->ensureDirectory(dirname($fullPath));

        // Handle stream resources
        if (is_resource($content)) {
            return $this->writeStream($path, $content);
        }

        $bytes = @file_put_contents($fullPath, $content, LOCK_EX);

        if ($bytes === false) {
            throw new WriteFailedException($path, 'write');
        }

        @chmod($fullPath, $this->filePermission);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $path): string
    {
        $fullPath = $this->pathValidator->resolve($path);

        if (!is_file($fullPath)) {
            throw new FileNotFoundException($path);
        }

        $content = @file_get_contents($fullPath);

        if ($content === false) {
            throw new FileNotFoundException($path);
        }

        return $content;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $path): bool
    {
        $fullPath = $this->pathValidator->resolve($path);

        if (!is_file($fullPath)) {
            return false;
        }

        if (!@unlink($fullPath)) {
            throw new WriteFailedException($path, 'delete');
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function exists(string $path): bool
    {
        $fullPath = $this->pathValidator->resolve($path);

        return is_file($fullPath);
    }

    /**
     * {@inheritDoc}
     */
    public function size(string $path): int
    {
        $fullPath = $this->pathValidator->resolve($path);

        if (!is_file($fullPath)) {
            throw new FileNotFoundException($path);
        }

        $size = @filesize($fullPath);

        if ($size === false) {
            throw new FileNotFoundException($path);
        }

        return $size;
    }

    /**
     * {@inheritDoc}
     */
    public function lastModified(string $path): int
    {
        $fullPath = $this->pathValidator->resolve($path);

        if (!is_file($fullPath)) {
            throw new FileNotFoundException($path);
        }

        $time = @filemtime($fullPath);

        if ($time === false) {
            throw new FileNotFoundException($path);
        }

        return $time;
    }

    // ------------------------------------------------------------------
    //  Copy / Move
    // ------------------------------------------------------------------

    /**
     * {@inheritDoc}
     */
    public function copy(string $from, string $to): bool
    {
        $sourcePath = $this->pathValidator->resolve($from);
        $destPath   = $this->pathValidator->resolve($to);

        if (!is_file($sourcePath)) {
            throw new FileNotFoundException($from);
        }

        $this->ensureDirectory(dirname($destPath));

        if (!@copy($sourcePath, $destPath)) {
            throw new WriteFailedException($to, 'copy');
        }

        @chmod($destPath, $this->filePermission);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function move(string $from, string $to): bool
    {
        $sourcePath = $this->pathValidator->resolve($from);
        $destPath   = $this->pathValidator->resolve($to);

        if (!is_file($sourcePath)) {
            throw new FileNotFoundException($from);
        }

        $this->ensureDirectory(dirname($destPath));

        if (!@rename($sourcePath, $destPath)) {
            throw new WriteFailedException($to, 'move');
        }

        return true;
    }

    // ------------------------------------------------------------------
    //  Directory operations
    // ------------------------------------------------------------------

    /**
     * {@inheritDoc}
     */
    public function files(string $directory = ''): array
    {
        $fullPath = $this->pathValidator->resolveDirectory($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $files  = [];
        $root   = $this->pathValidator->getRoot();
        $items  = @scandir($fullPath);

        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $fullPath . '/' . $item;
            if (is_file($itemPath)) {
                // Return paths relative to the storage root
                $files[] = $this->relativePath($itemPath, $root);
            }
        }

        sort($files);

        return $files;
    }

    /**
     * {@inheritDoc}
     */
    public function directories(string $directory = ''): array
    {
        $fullPath = $this->pathValidator->resolveDirectory($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $dirs  = [];
        $root  = $this->pathValidator->getRoot();
        $items = @scandir($fullPath);

        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $fullPath . '/' . $item;
            if (is_dir($itemPath)) {
                $dirs[] = $this->relativePath($itemPath, $root);
            }
        }

        sort($dirs);

        return $dirs;
    }

    /**
     * {@inheritDoc}
     */
    public function makeDirectory(string $path): bool
    {
        $fullPath = $this->pathValidator->resolve($path);

        if (is_dir($fullPath)) {
            return true;
        }

        if (!@mkdir($fullPath, $this->dirPermission, true)) {
            throw new WriteFailedException($path, 'create directory');
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function deleteDirectory(string $path): bool
    {
        $fullPath = $this->pathValidator->resolve($path);

        if (!is_dir($fullPath)) {
            return false;
        }

        $this->recursiveDelete($fullPath);

        return true;
    }

    // ------------------------------------------------------------------
    //  Streaming
    // ------------------------------------------------------------------

    /**
     * {@inheritDoc}
     */
    public function readStream(string $path)
    {
        $fullPath = $this->pathValidator->resolve($path);

        if (!is_file($fullPath)) {
            throw new FileNotFoundException($path);
        }

        $stream = @fopen($fullPath, 'rb');

        if ($stream === false) {
            throw new FileNotFoundException($path);
        }

        return $stream;
    }

    /**
     * {@inheritDoc}
     */
    public function writeStream(string $path, $resource): bool
    {
        if (!is_resource($resource)) {
            throw new \InvalidArgumentException('writeStream() expects a valid stream resource.');
        }

        $fullPath = $this->pathValidator->resolve($path);
        $this->ensureDirectory(dirname($fullPath));

        $target = @fopen($fullPath, 'wb');

        if ($target === false) {
            throw new WriteFailedException($path, 'write (stream)');
        }

        try {
            $bytes = @stream_copy_to_stream($resource, $target);

            if ($bytes === false) {
                throw new WriteFailedException($path, 'write (stream)');
            }

            @chmod($fullPath, $this->filePermission);

            return true;
        } finally {
            fclose($target);
        }
    }

    // ------------------------------------------------------------------
    //  Internal helpers
    // ------------------------------------------------------------------

    /**
     * Create a directory if it doesn't exist.
     */
    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            @mkdir($directory, $this->dirPermission, true);
        }
    }

    /**
     * Recursively delete a directory and all its contents.
     *
     * @throws WriteFailedException
     */
    private function recursiveDelete(string $directory): void
    {
        $items = @scandir($directory);

        if ($items === false) {
            throw new WriteFailedException($directory, 'delete directory');
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $directory . '/' . $item;

            if (is_dir($itemPath)) {
                $this->recursiveDelete($itemPath);
            } else {
                if (!@unlink($itemPath)) {
                    throw new WriteFailedException($itemPath, 'delete');
                }
            }
        }

        if (!@rmdir($directory)) {
            throw new WriteFailedException($directory, 'delete directory');
        }
    }

    /**
     * Calculate a relative path from the root.
     */
    private function relativePath(string $fullPath, string $root): string
    {
        $fullPath = str_replace('\\', '/', $fullPath);
        $root     = rtrim(str_replace('\\', '/', $root), '/') . '/';

        if (str_starts_with($fullPath, $root)) {
            return substr($fullPath, strlen($root));
        }

        return $fullPath;
    }
}
