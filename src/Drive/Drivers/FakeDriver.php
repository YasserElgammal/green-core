<?php

namespace YasserElgammal\Green\Drive\Drivers;

use YasserElgammal\Green\Drive\Contracts\DriveDriverInterface;
use YasserElgammal\Green\Drive\Exceptions\FileNotFoundException;
use YasserElgammal\Green\Drive\Exceptions\WriteFailedException;

/**
 * In-memory storage driver for testing.
 *
 * Stores all files in a PHP array — zero filesystem I/O.
 * Provides assertion methods for clean, expressive test code:
 *
 *   $fake = new FakeDriver();
 *   $fake->put('avatars/user1.png', $content);
 *   $fake->assertExists('avatars/user1.png');
 *   $fake->assertContentEquals('avatars/user1.png', $content);
 */
final class FakeDriver implements DriveDriverInterface
{
    /**
     * In-memory file store: path => content.
     * @var array<string, string>
     */
    private array $files = [];

    /**
     * In-memory directory store (tracks explicitly created dirs).
     * @var array<string, true>
     */
    private array $directories = [];

    // ------------------------------------------------------------------
    //  DriveDriverInterface implementation
    // ------------------------------------------------------------------

    public function put(string $path, mixed $content): bool
    {
        $path = $this->normalise($path);

        if (is_resource($content)) {
            $content = stream_get_contents($content);
            if ($content === false) {
                throw new WriteFailedException($path, 'write');
            }
        }

        $this->files[$path] = (string) $content;

        // Auto-register parent directories
        $this->registerParentDirectories($path);

        return true;
    }

    public function get(string $path): string
    {
        $path = $this->normalise($path);

        if (!isset($this->files[$path])) {
            throw new FileNotFoundException($path);
        }

        return $this->files[$path];
    }

    public function delete(string $path): bool
    {
        $path = $this->normalise($path);

        if (!isset($this->files[$path])) {
            return false;
        }

        unset($this->files[$path]);

        return true;
    }

    public function exists(string $path): bool
    {
        return isset($this->files[$this->normalise($path)]);
    }

    public function size(string $path): int
    {
        $content = $this->get($path);

        return strlen($content);
    }

    public function lastModified(string $path): int
    {
        $path = $this->normalise($path);

        if (!isset($this->files[$path])) {
            throw new FileNotFoundException($path);
        }

        // In-memory driver returns current time
        return time();
    }

    public function copy(string $from, string $to): bool
    {
        $content = $this->get($from);
        $this->put($to, $content);

        return true;
    }

    public function move(string $from, string $to): bool
    {
        $content = $this->get($from);
        $this->put($to, $content);
        $this->delete($from);

        return true;
    }

    public function files(string $directory = ''): array
    {
        $directory = $this->normalise($directory);
        $prefix    = $directory !== '' ? $directory . '/' : '';
        $files     = [];

        foreach (array_keys($this->files) as $path) {
            if ($prefix === '' || str_starts_with($path, $prefix)) {
                // Only direct children (no sub-directory files)
                $relative = $prefix !== '' ? substr($path, strlen($prefix)) : $path;
                if (!str_contains($relative, '/')) {
                    $files[] = $path;
                }
            }
        }

        sort($files);

        return $files;
    }

    public function directories(string $directory = ''): array
    {
        $directory = $this->normalise($directory);
        $prefix    = $directory !== '' ? $directory . '/' : '';
        $dirs      = [];

        foreach (array_keys($this->directories) as $dir) {
            if ($prefix === '' || str_starts_with($dir, $prefix)) {
                $relative = $prefix !== '' ? substr($dir, strlen($prefix)) : $dir;
                if ($relative !== '' && !str_contains($relative, '/')) {
                    $dirs[] = $dir;
                }
            }
        }

        sort($dirs);

        return array_values(array_unique($dirs));
    }

    public function makeDirectory(string $path): bool
    {
        $path = $this->normalise($path);
        $this->directories[$path] = true;
        $this->registerParentDirectories($path . '/placeholder');

        return true;
    }

    public function deleteDirectory(string $path): bool
    {
        $path   = $this->normalise($path);
        $prefix = $path . '/';

        // Remove all files in the directory
        foreach (array_keys($this->files) as $filePath) {
            if (str_starts_with($filePath, $prefix)) {
                unset($this->files[$filePath]);
            }
        }

        // Remove the directory and all subdirectories
        foreach (array_keys($this->directories) as $dirPath) {
            if ($dirPath === $path || str_starts_with($dirPath, $prefix)) {
                unset($this->directories[$dirPath]);
            }
        }

        return true;
    }

    public function readStream(string $path)
    {
        $content = $this->get($path);
        $stream  = fopen('php://memory', 'r+b');

        if ($stream === false) {
            throw new FileNotFoundException($path);
        }

        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }

    public function writeStream(string $path, $resource): bool
    {
        if (!is_resource($resource)) {
            throw new \InvalidArgumentException('writeStream() expects a valid stream resource.');
        }

        $content = stream_get_contents($resource);

        if ($content === false) {
            throw new WriteFailedException($path, 'write (stream)');
        }

        return $this->put($path, $content);
    }

    // ------------------------------------------------------------------
    //  Test assertion helpers
    // ------------------------------------------------------------------

    /**
     * Assert a file exists in the fake storage.
     *
     * @throws \PHPUnit\Framework\AssertionFailedError|\RuntimeException
     */
    public function assertExists(string $path): void
    {
        $path = $this->normalise($path);

        if (!isset($this->files[$path])) {
            $this->fail("Expected file [{$path}] to exist, but it does not.");
        }
    }

    /**
     * Assert a file does NOT exist in the fake storage.
     *
     * @throws \PHPUnit\Framework\AssertionFailedError|\RuntimeException
     */
    public function assertMissing(string $path): void
    {
        $path = $this->normalise($path);

        if (isset($this->files[$path])) {
            $this->fail("Expected file [{$path}] to be missing, but it exists.");
        }
    }

    /**
     * Assert a file exists and contains the expected content.
     *
     * @throws \PHPUnit\Framework\AssertionFailedError|\RuntimeException
     */
    public function assertContentEquals(string $path, string $expected): void
    {
        $this->assertExists($path);

        $actual = $this->files[$this->normalise($path)];

        if ($actual !== $expected) {
            $this->fail(
                "File [{$path}] content does not match.\n"
                . "Expected: " . mb_substr($expected, 0, 100) . "\n"
                . "Actual:   " . mb_substr($actual, 0, 100)
            );
        }
    }

    /**
     * Assert the fake storage has exactly the given number of files.
     *
     * @throws \PHPUnit\Framework\AssertionFailedError|\RuntimeException
     */
    public function assertCount(int $expected): void
    {
        $actual = count($this->files);

        if ($actual !== $expected) {
            $this->fail("Expected {$expected} files in fake storage, but found {$actual}.");
        }
    }

    /**
     * Get all stored file paths (useful for debugging tests).
     *
     * @return string[]
     */
    public function allFiles(): array
    {
        return array_keys($this->files);
    }

    /**
     * Reset the fake storage to a clean state.
     */
    public function flush(): void
    {
        $this->files       = [];
        $this->directories = [];
    }

    // ------------------------------------------------------------------
    //  Internals
    // ------------------------------------------------------------------

    /**
     * Normalise a path: forward slashes, no leading/trailing slashes.
     */
    private function normalise(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        // Collapse multiple slashes
        return (string) preg_replace('#/+#', '/', $path);
    }

    /**
     * Register all parent directories for a file path.
     */
    private function registerParentDirectories(string $path): void
    {
        $parts = explode('/', $path);
        array_pop($parts); // Remove the filename

        $current = '';
        foreach ($parts as $part) {
            $current = $current === '' ? $part : $current . '/' . $part;
            $this->directories[$current] = true;
        }
    }

    /**
     * Trigger an assertion failure using PHPUnit if available, otherwise RuntimeException.
     *
     * @throws \RuntimeException
     */
    private function fail(string $message): void
    {
        // Use PHPUnit's assertion if available (test environment)
        if (class_exists(\PHPUnit\Framework\Assert::class)) {
            \PHPUnit\Framework\Assert::fail($message);
        }

        throw new \RuntimeException($message);
    }
}
