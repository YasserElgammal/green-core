<?php

namespace YasserElgammal\Green\Tests\Config;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Config\Repository;
use YasserElgammal\Green\Config\Exceptions\ConfigurationException;
use YasserElgammal\Green\Config\Security\SecretRedactor;
use YasserElgammal\Green\Config\Sources\PhpDirectorySource;

final class RepositoryTest extends TestCase
{
    public function test_it_reads_and_changes_nested_values_at_runtime(): void
    {
        $config = new Repository(['app' => ['debug' => false]]);

        self::assertFalse($config->get('app.debug'));
        self::assertSame('fallback', $config->get('missing', 'fallback'));

        $config->set('app.debug', true);

        self::assertTrue($config->get('app.debug'));
        self::assertTrue($config->has('app.debug'));
    }

    public function test_merge_recursively_overrides_defaults(): void
    {
        $config = new Repository(['mail' => ['host' => 'localhost', 'port' => 1025]]);

        $config->merge(['mail' => ['host' => 'smtp.example.com']]);

        self::assertSame('smtp.example.com', $config->get('mail.host'));
        self::assertSame(1025, $config->get('mail.port'));
    }

    public function test_directory_files_override_only_their_default_values(): void
    {
        $directory = sys_get_temp_dir() . '/green-config-' . bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory . '/app.php', "<?php return ['debug' => true];");

        try {
            $config = new Repository(['app' => ['debug' => false, 'locale' => 'en']]);
            $config->merge((new PhpDirectorySource($directory))->load());

            self::assertTrue($config->get('app.debug'));
            self::assertSame('en', $config->get('app.locale'));
        } finally {
            unlink($directory . '/app.php');
            rmdir($directory);
        }
    }

    public function test_lists_are_replaced_and_maps_are_merged(): void
    {
        $config = new Repository(['app' => ['providers' => ['A', 'B'], 'name' => 'Green']]);
        $config->merge(['app' => ['providers' => ['C']]]);

        self::assertSame(['C'], $config->get('app.providers'));
        self::assertSame('Green', $config->get('app.name'));
    }

    public function test_locked_repository_rejects_mutation(): void
    {
        $config = new Repository(['app' => ['debug' => false]]);
        $config->lock();

        $this->expectException(ConfigurationException::class);
        $config->set('app.debug', true);
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

    public function test_invalid_dot_key_is_rejected(): void
    {
        $this->expectException(ConfigurationException::class);
        (new Repository())->set('database..host', 'localhost');
    }
}
