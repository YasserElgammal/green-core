<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Config\ConfigCache;
use YasserElgammal\Green\Config\ConfigManager;
use YasserElgammal\Green\Config\Contracts\ConfigReaderInterface;
use YasserElgammal\Green\Config\Contracts\ConfigRedactorInterface;
use YasserElgammal\Green\Config\CoreDefinitions;
use YasserElgammal\Green\Config\DefinitionRegistry;
use YasserElgammal\Green\Config\Environment;
use YasserElgammal\Green\Config\Security\SecretRedactor;
use YasserElgammal\Green\Config\Typed\ApplicationConfig;
use YasserElgammal\Green\Config\Typed\CacheConfig;
use YasserElgammal\Green\Config\Typed\DatabaseConfig;
use YasserElgammal\Green\Config\Typed\LoggingConfig;
use YasserElgammal\Green\Config\Typed\MailConfig;
use YasserElgammal\Green\Config\Typed\TranslationConfig;
use YasserElgammal\Green\Config\Typed\ViewConfig;
use YasserElgammal\Green\Support\ServiceProvider;

final class ConfigServiceProvider extends ServiceProvider
{
    public function bootstrap(array $overrides = [], iterable $additionalDefinitions = []): ConfigManager
    {
        $basePath = $this->app->basePath();
        $configPath = (string) Environment::get('CONFIG_DIR', $this->app->basePath('config'));
        $cache = new ConfigCache($this->app->basePath(
            'bootstrap' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'config.php',
        ));
        $definitions = CoreDefinitions::registry($additionalDefinitions);
        $manager = new ConfigManager(
            $basePath,
            $configPath,
            $definitions,
            $cache,
        );
        $config = $manager->load($overrides);
        $manager->lock();

        $this->app->instance(ConfigReaderInterface::class, $config);
        $this->app->instance(ConfigCache::class, $cache);
        $this->app->instance(ConfigManager::class, $manager);
        $this->app->instance(DefinitionRegistry::class, $definitions);
        $this->app->instance(ConfigRedactorInterface::class, new SecretRedactor());
        $this->app->singleton(
            ApplicationConfig::class,
            fn () => ApplicationConfig::fromRepository($config),
        );
        $this->app->singleton(
            CacheConfig::class,
            fn () => CacheConfig::fromRepository($config),
        );
        $this->app->singleton(
            DatabaseConfig::class,
            fn () => DatabaseConfig::fromRepository($config),
        );
        $this->app->singleton(
            LoggingConfig::class,
            fn () => LoggingConfig::fromRepository($config),
        );
        $this->app->singleton(
            MailConfig::class,
            fn () => MailConfig::fromRepository($config),
        );
        $this->app->singleton(
            TranslationConfig::class,
            fn () => TranslationConfig::fromRepository($config, $basePath),
        );
        $this->app->singleton(
            ViewConfig::class,
            fn () => ViewConfig::fromRepository($config),
        );

        return $manager;
    }
}
