<?php

namespace YasserElgammal\Green\Cache\Drivers;

use YasserElgammal\Green\Cache\Contracts\CacheDriverInterface;

class FileDriver implements CacheDriverInterface
{
    public function __construct(private readonly string $directory)
    {
        $this->ensureDirectory($this->directory);
    }

    private function getFilePath(string $key, bool $createDirectory = true): string
    {
        $hash = hash('sha256', $key);
        $dir = $this->directory . DIRECTORY_SEPARATOR . substr($hash, 0, 2);

        if ($createDirectory) {
            $this->ensureDirectory($dir);
        }

        return $dir . DIRECTORY_SEPARATOR . substr($hash, 2);
    }

    public function get(string $key): mixed
    {
        $path = $this->getFilePath($key, false);

        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $item = @unserialize($content);
        if (!is_array($item) || !isset($item['expires_at'], $item['value'])) {
            $this->forget($key);
            return null;
        }

        if (time() >= $item['expires_at']) {
            $this->forget($key);
            return null;
        }

        return $item['value'];
    }

    public function put(string $key, mixed $value, int $ttl): void
    {
        $path = $this->getFilePath($key);
        $temporaryPath = $path . '.tmp.' . bin2hex(random_bytes(6));

        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl,
        ];

        if (file_put_contents($temporaryPath, serialize($data), LOCK_EX) === false) {
            throw new \RuntimeException("Failed to write cache file [{$temporaryPath}].");
        }

        if (file_exists($path)) {
            $this->makeWritable($path);
            if (!@unlink($path)) {
                if (file_put_contents($path, serialize($data), LOCK_EX) === false) {
                    @unlink($temporaryPath);
                    throw new \RuntimeException("Failed to replace existing cache file [{$path}].");
                }

                @unlink($temporaryPath);
                return;
            }
        }

        if (!rename($temporaryPath, $path)) {
            if (!copy($temporaryPath, $path)) {
                @unlink($temporaryPath);
                throw new \RuntimeException("Failed to move cache file [{$temporaryPath}] to [{$path}].");
            }

            @unlink($temporaryPath);
        }
    }

    public function forget(string $key): bool
    {
        $path = $this->getFilePath($key, false);

        if (!file_exists($path)) {
            return false;
        }

        $this->makeWritable($path);
        $deleted = @unlink($path);
        clearstatcache(true, $path);

        if ($deleted && !file_exists($path)) {
            return true;
        }

        $expired = serialize(['value' => null, 'expires_at' => 0]);
        file_put_contents($path, $expired, LOCK_EX);
        clearstatcache(true, $path);

        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function flush(): void
    {
        $this->deleteDirectory($this->directory);
        $this->ensureDirectory($this->directory);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $path = $item->getPathname();
            $this->makeWritable($path);

            if ($item->isDir()) {
                @rmdir($path);
                continue;
            }

            @unlink($path);
        }

        $this->makeWritable($dir);
        @rmdir($dir);
    }

    private function ensureDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create cache directory [{$dir}].");
        }
    }

    private function makeWritable(string $path): void
    {
        if (file_exists($path) && !is_writable($path)) {
            @chmod($path, 0666);
        }
    }
}