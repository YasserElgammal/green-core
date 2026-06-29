<?php

namespace YasserElgammal\Green;

use YasserElgammal\Green\Container\Container;
use YasserElgammal\Green\Config\Repository as ConfigRepository;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Routing\Router;
use YasserElgammal\Green\Exceptions\ExceptionHandler;
use YasserElgammal\Green\Support\ServiceProvider;

class Application extends Container
{
    private array $providers = [];
    public Router $router;

    public function __construct()
    {
        $this->instance(Application::class, $this);
        $this->instance(Container::class, $this);
        $GLOBALS['__green_app'] = $this;

        $this->loadConfiguration();
        $this->registerCoreProviders();
        $this->registerConfiguredProviders();
        $this->bootProviders();

        $this->router = $this->make(Router::class);
    }

    private function loadConfiguration(): void
    {
        $config = new ConfigRepository();
        
        $basePath = defined('BASE_PATH') ? rtrim(constant('BASE_PATH'), '/\\') : (getcwd() ?: '.');
        $configPath = $this->resolveEnv('CONFIG_DIR', $basePath . DIRECTORY_SEPARATOR . 'config');
        
        $config->loadDirectory($configPath);
        
        $this->instance('config', $config);
        $this->instance(ConfigRepository::class, $config);
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
        $config = $this->make('config');
        $providers = $config->get('app.providers', []);

        foreach ($providers as $provider) {
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
        $exceptionHandler = $this->make(ExceptionHandler::class);

        try {
            return $this->router->dispatch($request);
        } catch (\Throwable $e) {
            return $exceptionHandler->handle($e, $request);
        }
    }

    private function resolveEnv(string $key, string $default): string
    {
        if (!empty($_ENV[$key])) {
            return $_ENV[$key];
        }

        $value = getenv($key);
        return ($value !== false && $value !== '') ? $value : $default;
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
