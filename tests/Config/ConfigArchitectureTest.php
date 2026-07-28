<?php

namespace YasserElgammal\Green\Tests\Config;

use PHPUnit\Framework\TestCase;

final class ConfigArchitectureTest extends TestCase
{
    public function test_environment_access_is_confined_to_environment_adapter(): void
    {
        $violations = $this->scan('/\$_ENV|getenv\s*\(/', [
            'Config' . DIRECTORY_SEPARATOR . 'Environment.php',
            'Console' . DIRECTORY_SEPARATOR . 'Stubs',
        ]);

        self::assertSame([], $violations, 'Direct environment access found: ' . implode(', ', $violations));
    }

    public function test_runtime_code_does_not_resolve_config_by_string_alias(): void
    {
        $violations = $this->scan('/make\s*\(\s*[\'"]config[\'"]\s*\)/', []);

        self::assertSame([], $violations, 'String config service locator found: ' . implode(', ', $violations));
    }

    public function test_global_application_state_is_confined_to_composition_boundary(): void
    {
        $violations = $this->scan('/__green_app/', ['Application.php', 'helpers.php']);

        self::assertSame([], $violations, 'Global application access found: ' . implode(', ', $violations));
    }

    /** @param list<string> $allowed */
    private function scan(string $pattern, array $allowed): array
    {
        $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $violations = [];

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            $relative = substr($file->getPathname(), strlen($root) + 1);
            if ($this->isAllowed($relative, $allowed)) continue;
            if (preg_match($pattern, (string) file_get_contents($file->getPathname())) === 1) {
                $violations[] = $relative;
            }
        }

        sort($violations);
        return $violations;
    }

    private function isAllowed(string $path, array $allowed): bool
    {
        foreach ($allowed as $entry) {
            if ($path === $entry || str_starts_with($path, $entry . DIRECTORY_SEPARATOR)) return true;
        }
        return false;
    }
}
