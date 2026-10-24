<?php

namespace YasserElgammal\Green;

use YasserElgammal\Green\Connect\Connect;
use YasserElgammal\Green\Connect\ConnectManager;
use YasserElgammal\Green\Container\Container;
use YasserElgammal\Green\Config\Typed\ApplicationConfig;
use YasserElgammal\Green\ErrorHandling\GreenErrorKernel;
use YasserElgammal\Green\Http\{Request, Response};
use YasserElgammal\Green\Logging\LogManager;
use YasserElgammal\Green\Routing\Router;
use YasserElgammal\Green\Exceptions\ExceptionHandler;
use YasserElgammal\Green\Signal\{LifecycleSignals, SignalDispatcher};
use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\Auth\Auth;
use YasserElgammal\Green\Providers\{
    ConfigServiceProvider,
    LogServiceProvider,
    ErrorServiceProvider,
    DriveServiceProvider,
    ConnectServiceProvider,
    RoutingServiceProvider,
    ValidationServiceProvider,
    ViewServiceProvider,
    TranslationServiceProvider,
    SignalServiceProvider,
    AuthServiceProvider,
    SessionServiceProvider,
    DatabaseServiceProvider,
    CacheServiceProvider,
    QueueServiceProvider,
};

class Application extends Container
{
    private array $providers = [];
    private int $handlingDepth = 0;
    private string $basePath;
    public Router $router;

    public function __construct(
        array $configOverrides = [],
        ?string $basePath = null,
        iterable $configDefinitions = [],
    ) {
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
        (new ConfigServiceProvider($this))
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
            LogServiceProvider::class,
            ErrorServiceProvider::class,
            DriveServiceProvider::class,
            ConnectServiceProvider::class,
            RoutingServiceProvider::class,
            ValidationServiceProvider::class,
            ViewServiceProvider::class,
            TranslationServiceProvider::class,
            SignalServiceProvider::class,
            AuthServiceProvider::class,
            SessionServiceProvider::class,
            DatabaseServiceProvider::class,
            CacheServiceProvider::class,
            QueueServiceProvider::class,
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
        $auth = $this->has(Auth::class) ? $this->make(Auth::class) : new Auth();
        $auth->beginRequest($request);
        $nested = $this->handlingDepth > 0;
        $this->handlingDepth++;
        $previousRequest = $this->instances[Request::class] ?? null;
        $this->instance(Request::class, $request);

        try {
            $signals = $this->make(SignalDispatcher::class);
            try {
                $signals->emit(LifecycleSignals::REQUEST_RECEIVED, ['request' => $request]);
                $response = $this->router->dispatch($request);
            } catch (\Throwable $e) {
                $this->emitLifecycleSignalSafely($signals, LifecycleSignals::EXCEPTION_OCCURRED, [
                    'request' => $request,
                    'exception' => $e,
                ]);

                try {
                    $response = $this->make(ExceptionHandler::class)->handle($e, $request);
                } catch (\Throwable $handlerError) {
                    error_log(sprintf(
                        '[Green] Exception handler failed: %s; original error: %s',
                        $handlerError->getMessage(),
                        $e->getMessage(),
                    ));

                    $response = new Response(
                        'Internal Server Error',
                        500,
                        ['Content-Type' => 'text/plain; charset=UTF-8'],
                    );
                }
            }

            $this->emitLifecycleSignalSafely($signals, LifecycleSignals::REQUEST_HANDLED, [
                'request' => $request,
                'response' => $response,
            ]);

            return $response;
        } finally {
            $auth->endRequest($request);
            $this->handlingDepth--;

            if ($nested && $previousRequest instanceof Request) {
                $this->instance(Request::class, $previousRequest);
            } elseif ($nested) {
                unset($this->instances[Request::class]);
            }
        }
    }

    private function emitLifecycleSignalSafely(
        SignalDispatcher $signals,
        string $signal,
        array $payload,
    ): void {
        try {
            $signals->emit($signal, $payload);
        } catch (\Throwable $e) {
            error_log(sprintf('[Green] Signal [%s] listener failed: %s', $signal, $e->getMessage()));
        }
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path === '' ? '' : DIRECTORY_SEPARATOR . ltrim($path, '/\\'));
    }

    // --- Backward Compatibility Methods ---

    public function getLogManager(): LogManager
    {
        return $this->make(LogManager::class);
    }

    public function getErrorKernel(): GreenErrorKernel
    {
        return $this->make(GreenErrorKernel::class);
    }

    public function getConnect(): Connect
    {
        return $this->make(Connect::class);
    }

    public function getConnectManager(): ConnectManager
    {
        return $this->make(ConnectManager::class);
    }
}
