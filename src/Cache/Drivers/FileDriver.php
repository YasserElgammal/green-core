<?php

namespace YasserElgammal\Green\Cache\Drivers;

use YasserElgammal\Green\Cache\Contracts\CacheDriverInterface;

class FileDriver implements CacheDriverInterface
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0777, true);
        }
    }

    private function getFilePath(string $key): string
    {
        $hash = hash('sha256', $key);
        $dir = $this->directory . DIRECTORY_SEPARATOR . substr($hash, 0, 2);

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir . DIRECTORY_SEPARATOR . substr($hash, 2);
    }

    public function get(string $key): mixed
    {
        $path = $this->getFilePath($key);

        if (!file_exists($path)) {
            return null;
        }

        $content = @file_get_contents($path);
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

        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl,
        ];

        @file_put_contents($path, serialize($data), LOCK_EX);
    }

    public function forget(string $key): bool
    {
        $path = $this->getFilePath($key);

        if (file_exists($path)) {
            return @unlink($path);
        }

        return false;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function flush(): void
    {
        $this->deleteDirectory($this->directory);
        @mkdir($this->directory, 0777, true);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
