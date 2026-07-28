<?php

namespace YasserElgammal\Green\Debug;

use YasserElgammal\Green\Config\Environment;

final class DebugConfig
{
    public function __construct(
        public readonly int $maxDepth = 5,
        public readonly int $maxItems = 50,
        public readonly int $maxStringLength = 10000,
        public readonly bool $darkTheme = true,
    ) {
    }

    /**
     * @param array{
     *     max_depth?: int,
     *     max_items?: int,
     *     max_string_length?: int,
     *     dark_theme?: bool
     * } $overrides
     */
    public static function fromArray(array $overrides = []): self
    {
        return new self(
            maxDepth: max(1, (int) ($overrides['max_depth'] ?? self::envInt('GREEN_LEAF_DEPTH', 5))),
            maxItems: max(1, (int) ($overrides['max_items'] ?? self::envInt('GREEN_LEAF_ITEMS', 50))),
            maxStringLength: max(64, (int) ($overrides['max_string_length'] ?? self::envInt('GREEN_LEAF_STRING_LIMIT', 10000))),
            darkTheme: (bool) ($overrides['dark_theme'] ?? self::envBool('GREEN_LEAF_DARK', true)),
        );
    }

    public static function fromProjectConfig(): self
    {
        $configFile = self::resolveConfigFile();

        if (!is_file($configFile)) {
            return self::fromArray();
        }

        $config = require $configFile;

        return self::fromArray(is_array($config) ? $config : []);
    }

    private static function resolveConfigFile(): string
    {
        $configured = Environment::get(
            'GREEN_LEAF_CONFIG',
            Environment::get('LEAF_CONFIG', 'config/leaf.php'),
        );

        $configured = (string) $configured;

        if (str_starts_with($configured, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $configured)) {
            return $configured;
        }

        $basePath = defined('BASE_PATH') ? rtrim((string) constant('BASE_PATH'), '/\\') : (getcwd() ?: '.');

        return $basePath . DIRECTORY_SEPARATOR . ltrim($configured, '/\\');
    }

    private static function envInt(string $key, int $default): int
    {
        return Environment::int($key, $default);
    }

    private static function envBool(string $key, bool $default): bool
    {
        return Environment::bool($key, $default);
    }
}
