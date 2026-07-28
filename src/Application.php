<?php

namespace YasserElgammal\Green;

use YasserElgammal\Green\Container\Container;
use YasserElgammal\Green\Config\Typed\ApplicationConfig;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Routing\Router;
use YasserElgammal\Green\Exceptions\ExceptionHandler;
use YasserElgammal\Green\Support\ServiceProvider;

class Application extends Container
{
    private array $providers = [];
    private string $basePath;
    public Router $router;

    public function __construct(
        array $configOverrides = [],
        ?string $basePath = null,
        iterable $configDefinitions = [],
    )
    {
        $this->basePath = $this->resolveBasePath($basePath);

        $this->instance(Application::class, $this);
        $this->instance(Container::class, $this);
        $GLOBALS['__green_app'] = $this;

        $this->loadConfiguration($configOverrides, $configDefinitions);
        $this->registerCoreProviders();
        $this->registerConfiguredProviders();
        $this->bootProviders();

        $this->router = $this->make(Router::class);
    }

    private function loadConfiguration(array $overrides, iterable $configDefinitions = []): void
    {
        (new \YasserElgammal\Green\Providers\ConfigServiceProvider($this))
            ->bootstrap($overrides, $configDefinitions);
    }

    /**
     * Return the consumer application's root directory.
     *
     * Passing the path explicitly is preferred. BASE_PATH and the current
     * working directory are supported composition-root conventions.
     */
    private function resolveBasePath(?string $basePath): string
    {
        $path = $basePath
            ?? (defined('BASE_PATH') ? (string) constant('BASE_PATH') : null)
            ?? (getcwd() ?: '.');

        return rtrim($path, '/\\');
    }

    private function registerCoreProviders(): void
    {
        $providers = [
            \YasserElgammal\Green\Providers\LogServiceProvider::class,
            \YasserElgammal\Green\Providers\ErrorServiceProvider::class,
            \YasserElgammal\Green\Providers\DriveServiceProvider::class,
            \YasserElgammal\Green\Providers\ConnectServiceProvider::class,
            \YasserElgammal\Green\Providers\RoutingServiceProvider::class,
            \YasserElgammal\Green\Providers\ValidationServiceProvider::class,
            \YasserElgammal\Green\Providers\ViewServiceProvider::class,
            \YasserElgammal\Green\Providers\TranslationServiceProvider::class,
            \YasserElgammal\Green\Providers\SignalServiceProvider::class,
            \YasserElgammal\Green\Providers\AuthServiceProvider::class,
            \YasserElgammal\Green\Providers\DatabaseServiceProvider::class,
            \YasserElgammal\Green\Providers\CacheServiceProvider::class,
        ];

        foreach ($providers as $provider) {
            $this->register(new $provider($this));
        }
    }

    private function registerConfiguredProviders(): void
    {
        $settings = $this->make(ApplicationConfig::class);

        foreach ($settings->providers as $provider) {
            if (class_exists($provider)) {
                $this->register(new $provider($this));
            }
        }
    }

    public function register(ServiceProvider $provider): void
    {
        $provider->register();
        $this->providers[] = $provider;
    }

    public function bootProviders(): void
    {
        foreach ($this->providers as $provider) {
            $provider->boot();
        }
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (\Throwable $e) {
            try {
                return $this->make(ExceptionHandler::class)->handle($e, $request);
            } catch (\Throwable $handlerError) {
                // Error rendering depends on infrastructure too (container, logger,
                // templates), so it needs an independent last-resort response.
                error_log(sprintf(
                    '[Green] Exception handler failed: %s; original error: %s',
                    $handlerError->getMessage(),
                    $e->getMessage(),
                ));

                return new Response(
                    'Internal Server Error',
                    500,
                    ['Content-Type' => 'text/plain; charset=UTF-8'],
                );
            }
        }
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path === '' ? '' : DIRECTORY_SEPARATOR . ltrim($path, '/\\'));
    }

    // --- Backward Compatibility Methods ---

    public function getLogManager(): \YasserElgammal\Green\Logging\LogManager
    {
        return $this->make(\YasserElgammal\Green\Logging\LogManager::class);
    }

    public function getErrorKernel(): \YasserElgammal\Green\ErrorHandling\GreenErrorKernel
    {
        return $this->make(\YasserElgammal\Green\ErrorHandling\GreenErrorKernel::class);
    }

    public function getConnect(): \YasserElgammal\Green\Connect\Connect
    {
        return $this->make(\YasserElgammal\Green\Connect\Connect::class);
    }

    public function getConnectManager(): \YasserElgammal\Green\Connect\ConnectManager
    {
        return $this->make(\YasserElgammal\Green\Connect\ConnectManager::class);
    }
}
