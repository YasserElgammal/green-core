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
        static $session = null;
        if ($session === null) {
            $session = new SessionManager();
        }
        return $session;
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
    /**
     * Register the LogManager instance for use by the green_log() helper.
     *
     * Called once by Application during bootstrap. This avoids static
     * methods on classes while giving the helper access to the log system.
     *
     * @param LogManager $manager  The application's LogManager instance
     */
    function green_log_set_manager(LogManager $manager): void
    {
        // Store in a static variable — same pattern used by session()
        static $stored = false;
        if (!$stored) {
            $GLOBALS['__green_log_manager'] = $manager;
            $stored = true;
        }
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
        $manager = $GLOBALS['__green_log_manager'] ?? null;

        if (!$manager instanceof LogManager) {
            // Fallback if the logging system hasn't been booted yet
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
    /**
     * Register the Drive instance for use by the drive() helper.
     *
     * @param Drive $drive
     */
    function drive_set_instance(Drive $drive): void
    {
        static $stored = false;
        if (!$stored) {
            $GLOBALS['__green_drive_instance'] = $drive;
            $stored = true;
        }
    }
}

if (!function_exists('drive')) {
    /**
     * Get the global Drive instance.
     *
     * @return Drive
     */
    function drive(): Drive
    {
        $drive = $GLOBALS['__green_drive_instance'] ?? null;
        if (!$drive instanceof Drive) {
            throw new \RuntimeException('Drive has not been initialized.');
        }
        return $drive;
    }
}
