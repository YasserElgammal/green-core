<?php

namespace YasserElgammal\Green\Translation;

use YasserElgammal\Green\Database\Database;
use YasserElgammal\Green\Translation\Cache\FileTranslationCache;
use YasserElgammal\Green\Translation\Cache\InMemoryTranslationCache;
use YasserElgammal\Green\Translation\Contracts\LocaleResolverInterface;
use YasserElgammal\Green\Translation\Contracts\TranslationCacheInterface;
use YasserElgammal\Green\Translation\Contracts\TranslationProviderInterface;
use YasserElgammal\Green\Translation\Plural\PluralRuleFactory;
use YasserElgammal\Green\Translation\Provider\DatabaseProvider;
use YasserElgammal\Green\Translation\Provider\JsonFileProvider;
use YasserElgammal\Green\Translation\Resolver\ChainLocaleResolver;
use YasserElgammal\Green\Translation\Resolver\RequestLocaleResolver;
use YasserElgammal\Green\Translation\Resolver\SystemLocaleResolver;

/**
 * Factory and configuration hub for the translation engine.
 *
 * Provides a convenient static create() method that wires up
 * all components with sensible defaults, plus a fluent builder
 * API for advanced configuration.
 *
 * Quick start:
 *   $translator = TranslatorManager::create([
 *       'lang_path' => __DIR__ . '/lang',
 *   ]);
 *
 * Advanced:
 *   $translator = TranslatorManager::builder()
 *       ->defaultLocale('ar')
 *       ->langPath('/app/lang')
 *       ->withDatabase()
 *       ->withFileCache('/app/storage/cache/translations')
 *       ->build();
 */
final class TranslatorManager
{
    /** Singleton Translator instance for the global helpers. */
    private static ?Translator $instance = null;

    // ── Builder state ──────────────────────────────────────────

    private string $defaultLocale = 'en';
    private ?string $fallbackLocale = null;
    private ?string $langPath = null;
    private ?string $cachePath = null;
    private bool $useDatabase = false;


    /** @var TranslationProviderInterface[] */
    private array $customProviders = [];

    /** @var LocaleResolverInterface[] */
    private array $customResolvers = [];

    private ?TranslationCacheInterface $customCache = null;
    private ?PluralRuleFactory $customPluralFactory = null;

    /** @var string[]|null */
    private ?array $allowedLocales = null;

    // ── Static factory ─────────────────────────────────────────

    /**
     * Create a Translator with a simple configuration array.
     *
     * Supported keys:
     *   - 'default_locale'  (string)  Default locale code.           Default: 'en'
     *   - 'fallback_locale' (string)  Fallback locale code.          Default: null
     *   - 'lang_path'       (string)  Path to lang/ directory.       Required.
     *   - 'cache_path'      (string)  Path for file cache.           Default: null (in-memory only)
     *   - 'providers'       (array)   Provider names: 'json','database'. Default: ['json']
     *   - 'allowed_locales' (array)   Whitelist of accepted locales.
     *
     * @param array<string,mixed> $config
     */
    public static function create(array $config = []): Translator
    {
        $builder = self::builder();

        if (isset($config['default_locale'])) {
            $builder->defaultLocale($config['default_locale']);
        }

        if (isset($config['fallback_locale'])) {
            $builder->fallbackLocale($config['fallback_locale']);
        }

        if (isset($config['lang_path'])) {
            $builder->langPath($config['lang_path']);
        }

        if (isset($config['cache_path'])) {
            $builder->withFileCache($config['cache_path']);
        }

        if (isset($config['allowed_locales'])) {
            $builder->allowedLocales($config['allowed_locales']);
        }

        $providerNames = $config['providers'] ?? ['json'];

        if (in_array('database', $providerNames, true)) {
            $builder->withDatabase();
        }


        return $builder->build();
    }

    /**
     * Start a fluent builder.
     */
    public static function builder(): self
    {
        return new self();
    }

    // ── Fluent builder methods ─────────────────────────────────

    public function defaultLocale(string $locale): self
    {
        $this->defaultLocale = $locale;
        return $this;
    }

    public function fallbackLocale(string $locale): self
    {
        $this->fallbackLocale = $locale;
        return $this;
    }

    public function langPath(string $path): self
    {
        $this->langPath = $path;
        return $this;
    }

    public function withDatabase(): self
    {
        $this->useDatabase = true;
        return $this;
    }


    public function withFileCache(string $cachePath): self
    {
        $this->cachePath = $cachePath;
        return $this;
    }

    public function withCache(TranslationCacheInterface $cache): self
    {
        $this->customCache = $cache;
        return $this;
    }

    public function addProvider(TranslationProviderInterface $provider): self
    {
        $this->customProviders[] = $provider;
        return $this;
    }

    public function addResolver(LocaleResolverInterface $resolver): self
    {
        $this->customResolvers[] = $resolver;
        return $this;
    }

    public function withPluralFactory(PluralRuleFactory $factory): self
    {
        $this->customPluralFactory = $factory;
        return $this;
    }

    public function allowedLocales(array $locales): self
    {
        $this->allowedLocales = $locales;
        return $this;
    }

    /**
     * Build and return the fully configured Translator.
     */
    public function build(): Translator
    {
        // ── Providers (ordered: custom → database → json → remote) ──
        $providers = [];

        foreach ($this->customProviders as $provider) {
            $providers[] = $provider;
        }

        if ($this->useDatabase) {
            $providers[] = new DatabaseProvider(Database::getConnection());
        }

        if ($this->langPath !== null) {
            $providers[] = new JsonFileProvider($this->langPath);
        }



        // ── Locale resolvers ──
        $resolvers = [];

        foreach ($this->customResolvers as $resolver) {
            $resolvers[] = $resolver;
        }

        $resolvers[] = new RequestLocaleResolver(
            allowedLocales: $this->allowedLocales,
        );
        $resolvers[] = new SystemLocaleResolver($this->defaultLocale);

        $localeResolver = new ChainLocaleResolver($resolvers);

        // ── Cache ──
        $cache = $this->customCache;

        if ($cache === null && $this->cachePath !== null) {
            $cache = new FileTranslationCache($this->cachePath);
        }

        if ($cache === null) {
            $cache = new InMemoryTranslationCache();
        }

        // ── Plural rules ──
        $pluralFactory = $this->customPluralFactory ?? new PluralRuleFactory();

        // ── Fallback chain ──
        $fallbackChain = new FallbackChain(
            $providers,
            $this->defaultLocale,
            $this->fallbackLocale,
        );

        // ── Assemble ──
        return new Translator(
            fallbackChain:  $fallbackChain,
            localeResolver: $localeResolver,
            interpolator:   new Interpolator(),
            pluralFactory:  $pluralFactory,
            cache:          $cache,
            defaultLocale:  $this->defaultLocale,
            fallbackLocale: $this->fallbackLocale,
        );
    }

    // ── Singleton access for global helpers ─────────────────────

    /**
     * Get or create the global Translator instance.
     *
     * Used by the t(), and trans_choice() helpers.
     * Call setInstance() to provide a pre-configured translator.
     */
    public static function getInstance(): Translator
    {
        if (self::$instance === null) {
            $basePath  = defined('BASE_PATH') ? BASE_PATH : getcwd();
            $langPath  = $_ENV['APP_LANG_PATH'] ?? 'lang';
            $cachePath = $_ENV['APP_TRANSLATION_CACHE_PATH'] ?? null;

            // Resolve relative paths against the project root.
            if ($langPath !== null && !self::isAbsolutePath($langPath)) {
                $langPath = $basePath . DIRECTORY_SEPARATOR . $langPath;
            }

            if ($cachePath !== null && !self::isAbsolutePath($cachePath)) {
                $cachePath = $basePath . DIRECTORY_SEPARATOR . $cachePath;
            }

            // Auto-create with environment-based defaults.
            self::$instance = self::create([
                'default_locale'  => $_ENV['APP_LOCALE'] ?? 'en',
                'fallback_locale' => $_ENV['APP_FALLBACK_LOCALE'] ?? 'en',
                'lang_path'       => $langPath,
                'cache_path'      => $cachePath,
            ]);
        }

        return self::$instance;
    }

    /**
     * Check whether a path is absolute.
     */
    private static function isAbsolutePath(string $path): bool
    {
        // Unix absolute or Windows absolute (e.g. C:\, D:\, \\server)
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    /**
     * Inject a pre-configured Translator as the global singleton.
     *
     * Useful for testing and manual bootstrapping.
     */
    public static function setInstance(?Translator $translator): void
    {
        self::$instance = $translator;
    }
}
