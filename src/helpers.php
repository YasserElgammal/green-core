<?php

use YasserElgammal\Green\Database\Model;
use YasserElgammal\Green\Http\JsonResponse;
use YasserElgammal\Green\Pagination\Paginator;
use YasserElgammal\Green\Transformer\Transformer;
use YasserElgammal\Green\Transformer\TransformerResponse;
use YasserElgammal\Green\View\View;
use YasserElgammal\Green\Session\SessionManager;
use YasserElgammal\Green\Http\RedirectResponse;
use YasserElgammal\Green\Translation\TranslatorManager;
use YasserElgammal\Green\Security\Csrf\CsrfConfig;
use YasserElgammal\Green\Security\Csrf\CsrfTokenManager;
use YasserElgammal\Green\ErrorHandling\ErrorRecord;
use YasserElgammal\Green\ErrorHandling\RequestContext;
use YasserElgammal\Green\Logging\LogLevel;
use YasserElgammal\Green\Logging\LogManager;
use YasserElgammal\Green\Drive\Drive;
use YasserElgammal\Green\Connect\Connect;
use YasserElgammal\Green\Debug\DebugConfig;
use YasserElgammal\Green\Debug\DumpContext;
use YasserElgammal\Green\Debug\Dumper;
use YasserElgammal\Green\Debug\Renderers\CliRenderer;
use YasserElgammal\Green\Debug\Renderers\HtmlRenderer;
use YasserElgammal\Green\Signal\SignalDispatcher;
use YasserElgammal\Green\Auth\Authorizer;
use YasserElgammal\Green\Cache\CacheManager;

$GLOBALS['__green_started_at'] ??= $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);

if (!function_exists('app')) {
    function app(?string $abstract = null): mixed
    {
        $app = $GLOBALS['__green_app'] ?? null;
        if (!$app instanceof \YasserElgammal\Green\Application) {
            throw new \RuntimeException('Application has not been initialized.');
        }
        if ($abstract === null) {
            return $app;
        }
        return $app->make($abstract);
    }
}

if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        $config = app()->make(\YasserElgammal\Green\Config\Contracts\ConfigReaderInterface::class);
        if ($key === null) {
            return $config;
        }
        return $config->get($key, $default);
    }
}

if (!function_exists('leaf_config')) {
    /**
     * Configure the leaf() debug helper.
     *
     * Supported keys: max_depth, max_items, max_string_length, dark_theme.
     *
     * @param array<string,mixed>|null $config
     */
    function leaf_config(?array $config = null): DebugConfig
    {
        if ($config !== null) {
            $GLOBALS['__green_leaf_config'] = DebugConfig::fromArray($config);
        }

        $stored = $GLOBALS['__green_leaf_config'] ?? null;
        if ($stored instanceof DebugConfig) {
            return $stored;
        }

        return $GLOBALS['__green_leaf_config'] = DebugConfig::fromProjectConfig();
    }
}

if (!function_exists('leaf')) {
    /**
     * Dump a value with Green's native debugger and terminate execution.
     */
    function leaf(mixed $value): never
    {
        $config = leaf_config();
        $node = (new Dumper($config))->dump($value);
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? null;
        $context = DumpContext::capture($caller);
        $renderer = PHP_SAPI === 'cli' ? new CliRenderer() : new HtmlRenderer();

        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo $renderer->render($node, $context, $config);
        exit(1);
    }
}

if (!function_exists('response_json')) {
    function response_json(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status);
    }
}

if (!function_exists('paginate')) {
    function paginate(mixed $items, int $perPage, int $page): JsonResponse
    {
        $paginator = new Paginator();
        $result = $paginator->paginate($items, $perPage, $page);
        return new JsonResponse($result);
    }
}

if (!function_exists('transform')) {
    /**
     * Transform a model or collection through a Transformer.
     *
     * @param  Model|Model[]  $data
     * @param  Transformer    $transformer
     * @param  int            $status  HTTP status code
     * @return JsonResponse
     */
    function transform(Model|array $data, Transformer $transformer, int $status = 200): JsonResponse
    {
        if ($data instanceof Model) {
            return TransformerResponse::item($data, $transformer, $status);
        }

        return TransformerResponse::collection($data, $transformer, $status);
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): string
    {
        return View::render($template, $data);
    }
}

if (!function_exists('session')) {
    function session(): SessionManager
    {
        return app(SessionManager::class);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }
}

if (!function_exists('t')) {
    /**
     * Translate the given key.
     *
     * @param string              $key     Dot-notation translation key.
     * @param array<string,mixed> $replace Interpolation replacements.
     * @param string|null         $locale  Override locale (null = auto-resolve).
     *
     * @return string
     */
    function t(string $key, array $replace = [], ?string $locale = null): string
    {
        return TranslatorManager::getInstance()->get($key, $replace, $locale);
    }
}

if (!function_exists('trans_choice')) {
    /**
     * Translate the given key with pluralization.
     *
     * @param string              $key     Dot-notation translation key.
     * @param int                 $count   Count for pluralization.
     * @param array<string,mixed> $replace Interpolation replacements.
     * @param string|null         $locale  Override locale (null = auto-resolve).
     *
     * @return string
     */
    function trans_choice(string $key, int $count, array $replace = [], ?string $locale = null): string
    {
        return TranslatorManager::getInstance()->choice($key, $count, $replace, $locale);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Generate a new CSRF token pair.
     *
     * @return array{id: string, token: string}
     */
    function csrf_token(): array
    {
        $manager = new CsrfTokenManager(session(), new CsrfConfig());
        return $manager->generate();
    }
}

if (!function_exists('green_log_set_manager')) {
    /** @deprecated Bind LogManager through the application container instead. */
    function green_log_set_manager(LogManager $manager): void
    {
        app()->instance(LogManager::class, $manager);
    }
}
if (!function_exists('green_log')) {
    /**
     * Log a message manually through the Green logging system.
     *
     * Works at any log level. Context is automatically enriched with
     * request data via RequestContext::capture().
     *
     * @param string $message  The log message
     * @param string $level    Log level: debug, info, warning, error, critical
     * @param array  $context  Additional context to merge with auto-captured request data
     *
     * @example green_log('Payment failed for order #123', 'error');
     * @example green_log('Cache miss', 'debug', ['key' => 'users.list']);
     */
    function green_log(string $message, string $level = 'error', array $context = []): void
    {
        try {
            $manager = app(LogManager::class);
        } catch (\Throwable) {
            error_log("[Green] {$level}: {$message}");
            return;
        }

        try {
            $mergedContext = array_merge(RequestContext::capture(), $context);
            $record = ErrorRecord::fromManual($message, LogLevel::from($level), $mergedContext);
            $manager->log($record);
        } catch (\Throwable $e) {
            // Logging must never break application execution
            error_log("[Green] Failed to log: {$e->getMessage()} | Original: {$message}");
        }
    }
}

if (!function_exists('drive_set_instance')) {
    /** @deprecated Bind Drive through the application container instead. */
    function drive_set_instance(Drive $instance): void
    {
        app()->instance(Drive::class, $instance);
    }
}

if (!function_exists('drive')) {
    function drive(): Drive
    {
        return app(Drive::class);
    }
}

if (!function_exists('connect_set_instance')) {
    /** @deprecated Bind Connect through the application container instead. */
    function connect_set_instance(Connect $instance): void
    {
        app()->instance(Connect::class, $instance);
    }
}

if (!function_exists('connect')) {
    function connect(): Connect
    {
        return app(Connect::class);
    }
}

// ─── Phase 3: Signal Dispatcher ──────────────────────────────────────────────

if (!function_exists('signal_set_instance')) {
    /** @deprecated Bind SignalDispatcher through the application container instead. */
    function signal_set_instance(SignalDispatcher $instance): void
    {
        app()->instance(SignalDispatcher::class, $instance);
    }
}

if (!function_exists('signal')) {
    function signal(): SignalDispatcher
    {
        return app(SignalDispatcher::class);
    }
}

// ─── Phase 3: Authorizer ─────────────────────────────────────────────────────

if (!function_exists('authorizer_set_instance')) {
    /** @deprecated Bind Authorizer through the application container instead. */
    function authorizer_set_instance(Authorizer $instance): void
    {
        app()->instance(Authorizer::class, $instance);
    }
}

if (!function_exists('authorizer')) {
    function authorizer(): Authorizer
    {
        return app(Authorizer::class);
    }
}

// ─── Phase 3: Cache Manager ──────────────────────────────────────────────────

if (!function_exists('cache_set_instance')) {
    /** @deprecated Bind CacheManager through the application container instead. */
    function cache_set_instance(CacheManager $instance): void
    {
        app()->instance(CacheManager::class, $instance);
    }
}

if (!function_exists('cache')) {
    function cache(): CacheManager
    {
        return app(CacheManager::class);
    }
}

// ─── Phase 3: URL Generation ─────────────────────────────────────────────────

if (!function_exists('route')) {
    /**
     * Generate the URL to a named route.
     *
     * @param string $name
     * @param array $parameters
     * @return string
     */
    function route(string $name, array $parameters = []): string
    {
        /** @var \YasserElgammal\Green\Routing\Router $router */
        $router = app(\YasserElgammal\Green\Routing\Router::class);
        /** @var \YasserElgammal\Green\Http\Request $request */
        $request = app(\YasserElgammal\Green\Http\Request::class);

        $generator = new \YasserElgammal\Green\Routing\UrlGenerator($router, $request);
        return $generator->route($name, $parameters);
    }
}
