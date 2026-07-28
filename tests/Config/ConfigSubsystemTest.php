<?php

namespace YasserElgammal\Green\Tests\Config;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Config\ConfigCache;
use YasserElgammal\Green\Config\Loader;
use YasserElgammal\Green\Config\Sources\ArraySource;
use YasserElgammal\Green\Config\Sources\EnvironmentSource;
use YasserElgammal\Green\Config\Sources\PhpDirectorySource;
use YasserElgammal\Green\Config\Sources\PhpFileSource;
use YasserElgammal\Green\Config\CoreDefinitions;

final class ConfigSubsystemTest extends TestCase
{
    private string $directory;
    private bool $hadPort;
    private mixed $previousPort;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/green-config-system-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
        $this->hadPort = array_key_exists('GREEN_TEST_PORT', $_ENV);
        $this->previousPort = $_ENV['GREEN_TEST_PORT'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->hadPort) $_ENV['GREEN_TEST_PORT'] = $this->previousPort;
        else unset($_ENV['GREEN_TEST_PORT']);
        foreach (glob($this->directory . '/*') ?: [] as $file) unlink($file);
        rmdir($this->directory);
    }

    public function test_source_precedence_and_typed_environment_casting(): void
    {
        $_ENV['GREEN_TEST_PORT'] = '3307';
        file_put_contents($this->directory . '/database.php', "<?php return ['port' => 3308, 'options' => ['ssl' => true]];");

        $items = (new Loader())
            ->addSource(new ArraySource(['database' => ['port' => 3306, 'host' => 'localhost', 'options' => ['timeout' => 5]]]))
            ->addSource(new EnvironmentSource(['database.port' => ['env' => 'GREEN_TEST_PORT', 'type' => 'int']]))
            ->addSource(new PhpDirectorySource($this->directory))
            ->addSource(new ArraySource(['database' => ['port' => 3309]]))
            ->load();

        self::assertSame(3309, $items['database']['port']);
        self::assertSame('localhost', $items['database']['host']);
        self::assertSame(['timeout' => 5, 'ssl' => true], $items['database']['options']);
    }

    public function test_cached_snapshot_round_trips_exactly(): void
    {
        $path = $this->directory . '/config.php';
        $expected = ['app' => ['debug' => false], 'database' => ['port' => 3306]];
        $cache = new ConfigCache($path);

        $cache->write($expected);

        self::assertTrue($cache->exists());
        self::assertSame($expected, (new PhpFileSource($path))->load());

        $updated = ['app' => ['debug' => true]];
        $cache->write($updated);
        self::assertSame($updated, (new PhpFileSource($path))->load());

        self::assertTrue($cache->clear());
        self::assertFalse($cache->exists());
    }

    public function test_cache_compatibility_depends_on_definition_fingerprint(): void
    {
        $path = $this->directory . '/config.php';
        $cache = new ConfigCache($path);
        $fingerprint = CoreDefinitions::registry()->fingerprint($this->directory);

        $cache->write(['app' => ['debug' => false]], $fingerprint);

        self::assertTrue($cache->isCompatible($fingerprint));
        self::assertFalse($cache->isCompatible(str_repeat('a', 64)));
    }
}
