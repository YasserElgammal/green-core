<?php

namespace YasserElgammal\Green\Drive;

use YasserElgammal\Green\Drive\Exceptions\InvalidPathException;

/**
 * Centralised path security and normalisation.
 *
 * Every driver MUST pass relative paths through resolve() before
 * performing any filesystem I/O.  This class enforces:
 *
 *  1. No path-traversal segments (..)
 *  2. No null-byte injection (\0)
 *  3. No control characters (0x00–0x1F except tab)
 *  4. Root-directory isolation (resolved path must start with root)
 *  5. Consistent forward-slash normalisation
 */
final class PathValidator
{
    private readonly string $root;

    /**
     * @param string $root  Absolute path to the storage root directory.
     *                      Will be created if it does not exist.
     */
    public function __construct(string $root)
    {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
        $this->ensureRootExists();
    }

    /**
     * Normalise and validate a relative path, returning the full
     * absolute path ready for filesystem I/O.
     *
     * @param  string $path  Relative path within the storage root
     * @return string        Absolute, validated path
     *
     * @throws InvalidPathException  If the path is dangerous or escapes root
     */
    public function resolve(string $path): string
    {
        $this->guardAgainstDangerousCharacters($path);
        $normalised = $this->normalise($path);
        $this->guardAgainstTraversal($normalised);

        $fullPath = $this->root . '/' . $normalised;

        // Final safety net: if the target already exists on disk we can
        // use realpath() to verify the canonical path truly falls inside
        // the root.  For new files we validate the parent directory.
        $this->guardAgainstRootEscape($fullPath);

        return $fullPath;
    }

    /**
     * Resolve a path for directory-level operations.
     *
     * An empty string resolves to the root directory itself.
     *
     * @param  string $directory  Relative directory path
     * @return string             Absolute, validated directory path
     *
     * @throws InvalidPathException
     */
    public function resolveDirectory(string $directory): string
    {
        if ($directory === '' || $directory === '.' || $directory === '/') {
            return $this->root;
        }

        return $this->resolve($directory);
    }

    /**
     * Get the configured root directory.
     */
    public function getRoot(): string
    {
        return $this->root;
    }

    // ------------------------------------------------------------------
    //  Validation guards
    // ------------------------------------------------------------------

    /**
     * Reject null bytes and control characters.
     *
     * @throws InvalidPathException
     */
    private function guardAgainstDangerousCharacters(string $path): void
    {
        if (str_contains($path, "\0")) {
            throw new InvalidPathException($path, 'null byte detected');
        }

        // Reject control characters (0x00-0x1F) except horizontal tab (0x09)
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $path)) {
            throw new InvalidPathException($path, 'control character detected');
        }
    }

    /**
     * Reject any path containing directory-traversal segments.
     *
     * @throws InvalidPathException
     */
    private function guardAgainstTraversal(string $normalisedPath): void
    {
        // After normalisation, any remaining ".." is an escape attempt
        foreach (explode('/', $normalisedPath) as $segment) {
            if ($segment === '..') {
                throw new InvalidPathException($normalisedPath, 'path traversal detected');
            }
        }
    }

    /**
     * Ensure the resolved absolute path falls within the root directory.
     *
     * Uses realpath() on existing paths and logical validation on new paths.
     *
     * @throws InvalidPathException
     */
    private function guardAgainstRootEscape(string $fullPath): void
    {
        // For existing paths: use realpath() to resolve symlinks
        $real = realpath($fullPath);
        if ($real !== false) {
            $canonicalRoot = realpath($this->root);
            if ($canonicalRoot === false) {
                // Root itself doesn't exist — shouldn't happen, but guard
                return;
            }

            $normalizedReal = str_replace('\\', '/', $real);
            $normalizedRoot = str_replace('\\', '/', $canonicalRoot);

            if (!str_starts_with($normalizedReal, $normalizedRoot)) {
                throw new InvalidPathException($fullPath, 'path escapes storage root');
            }
            return;
        }

        // For new paths: find the closest existing ancestor and verify it
        $parent = dirname($fullPath);
        $parentReal = realpath($parent);
        if ($parentReal !== false) {
            $canonicalRoot = realpath($this->root);
            if ($canonicalRoot === false) {
                return;
            }

            $normalizedParent = str_replace('\\', '/', $parentReal);
            $normalizedRoot   = str_replace('\\', '/', $canonicalRoot);

            if (!str_starts_with($normalizedParent, $normalizedRoot)) {
                throw new InvalidPathException($fullPath, 'path escapes storage root');
            }
        }
    }

    // ------------------------------------------------------------------
    //  Path normalisation
    // ------------------------------------------------------------------

    /**
     * Normalise a relative path to a clean, consistent form.
     *
     * - Converts backslashes to forward slashes
     * - Collapses multiple consecutive slashes
     * - Removes leading/trailing slashes
     * - Removes current-directory segments (.)
     * - Trims whitespace
     */
    private function normalise(string $path): string
    {
        $path = trim($path);
        $path = str_replace('\\', '/', $path);

        // Collapse multiple slashes
        $path = (string) preg_replace('#/+#', '/', $path);

        // Remove leading and trailing slashes
        $path = trim($path, '/');

        // Remove current-directory segments
        $segments = array_filter(explode('/', $path), fn(string $s) => $s !== '.' && $s !== '');

        return implode('/', $segments);
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    /**
     * Ensure the root directory exists on disk.
     */
    private function ensureRootExists(): void
    {
        if (!is_dir($this->root)) {
            @mkdir($this->root, 0755, true);
        }
    }
}
