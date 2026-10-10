<?php

namespace YasserElgammal\Green\Tests\Config;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Config\Contracts\ConfigReaderInterface;
use YasserElgammal\Green\Config\Loader;
use YasserElgammal\Green\Config\Repository;
use YasserElgammal\Green\Config\Security\SecretRedactor;
use YasserElgammal\Green\Config\Sources\ArraySource;
use YasserElgammal\Green\Config\Sources\PhpDirectorySource;

final class RepositoryTest extends TestCase
{
    public function test_it_reads_nested_values(): void
    {
        $config = new Repository(['app' => ['debug' => false]]);

        self::assertFalse($config->get('app.debug'));
        self::assertSame('fallback', $config->get('missing', 'fallback'));
        self::assertTrue($config->has('app.debug'));
    }

    public function test_it_exposes_only_the_read_contract(): void
    {
        $config = new Repository(['app' => ['debug' => false]]);

        self::assertInstanceOf(ConfigReaderInterface::class, $config);
        self::assertFalse(method_exists($config, 'set'));
        self::assertFalse(method_exists($config, 'merge'));
        self::assertFalse(method_exists($config, 'lock'));
        self::assertFalse(method_exists($config, 'isLocked'));
    }

    public function test_directory_files_override_only_their_default_values(): void
    {
        $directory = sys_get_temp_dir() . '/green-config-' . bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory . '/app.php', "<?php return ['debug' => true];");

        try {
            $config = new Repository((new Loader())
                ->addSource(new ArraySource([
                    'app' => ['debug' => false, 'locale' => 'en'],
                ]))
                ->addSource(new PhpDirectorySource($directory))
                ->load());

            self::assertTrue($config->get('app.debug'));
            self::assertSame('en', $config->get('app.locale'));
        } finally {
            unlink($directory . '/app.php');
            rmdir($directory);
        }
    }

    public function test_secret_redactor_redacts_nested_secrets_without_mutating_repository(): void
    {
        $config = new Repository(['database' => ['password' => 'secret'], 'api_token' => 'token', 'host' => 'localhost']);
        $safe = (new SecretRedactor())->redact($config->all());

        self::assertSame('********', $safe['database']['password']);
        self::assertSame('********', $safe['api_token']);
        self::assertSame('localhost', $safe['host']);
        self::assertSame('secret', $config->all()['database']['password']);
    }
}
