<?php

namespace YasserElgammal\Green\Routing;

final class RouteCache
{
    public static function defaultPath(): string
    {
        $basePath = defined('BASE_PATH') ? rtrim((string) constant('BASE_PATH'), '/\\') : (getcwd() ?: '.');

        return $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'routes.php';
    }

    public static function ensureDirectoryExists(string $cacheFile): void
    {
        $directory = dirname($cacheFile);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    public static function clear(string $cacheFile): bool
    {
        if (!file_exists($cacheFile)) {
            return false;
        }

        return unlink($cacheFile);
    }
}
