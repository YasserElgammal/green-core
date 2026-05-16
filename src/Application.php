<?php

namespace YasserElgammal\Green;

use YasserElgammal\Green\Routing\Router;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Http\JsonResponse;
use YasserElgammal\Green\Http\ValidationException;
use YasserElgammal\Green\ErrorHandling\GreenErrorKernel;
use YasserElgammal\Green\Logging\LogManager;
use YasserElgammal\Green\Logging\Drivers\FileLogger;

class Application
{
    public Router $router;

    private GreenErrorKernel $errorKernel;

    private LogManager $logManager;

    public function __construct()
    {
        $this->bootErrorHandling();
        $this->router = new Router();
    }

    public function handle(Request $request): Response
    {
        return $this->router->dispatch($request);
    }

    /**
     * Get the LogManager instance for manual driver registration
     * or for injecting into other components (e.g. ExceptionHandler).
     */
    public function getLogManager(): LogManager
    {
        return $this->logManager;
    }

    /**
     * Get the error kernel instance.
     */
    public function getErrorKernel(): GreenErrorKernel
    {
        return $this->errorKernel;
    }

    /**
     * Bootstrap the global error handling system.
     *
     * Creates the LogManager with the default FileLogger driver,
     * then registers the GreenErrorKernel which installs all
     * PHP-level error handlers (exception, error, shutdown).
     *
     * Configuration via environment variables (.env):
     *
     *   LOG_DIR            — Log directory path        (default: storage/logs)
     *   LOG_LEVEL          — Minimum log level          (default: debug)
     *   LOG_MAX_DUPLICATES — Max same-error logs/request (default: 5, 0 = unlimited)
     *   LOG_RATE_LIMIT     — Max logs/fingerprint/window (default: 50)
     *   LOG_RATE_WINDOW    — Rate limit window seconds   (default: 60)
     */
    private function bootErrorHandling(): void
    {
        $logDir   = $this->resolveEnv('LOG_DIR', dirname(__DIR__) . '/storage/logs');
        $logLevel = $this->resolveLogLevel();

        $this->logManager = new LogManager();
        $this->logManager->addDriver(new FileLogger($logDir, $logLevel));

        // --- Deduplication config ---
        $maxDuplicates = (int) $this->resolveEnv('LOG_MAX_DUPLICATES', '5');
        $this->logManager->setMaxDuplicates($maxDuplicates);

        // --- Rate limiting config ---
        $rateLimit  = (int) $this->resolveEnv('LOG_RATE_LIMIT', '50');
        $rateWindow = (int) $this->resolveEnv('LOG_RATE_WINDOW', '60');
        if ($rateLimit > 0) {
            $rateLimitDir = $logDir . '/rate-limits';
            $this->logManager->setRateLimit($rateLimit, $rateWindow, $rateLimitDir);
        }

        // Register the LogManager with the green_log() helper function
        green_log_set_manager($this->logManager);

        $this->errorKernel = new GreenErrorKernel($this->logManager);
        $this->errorKernel->register();
    }

    /**
     * Resolve the minimum log level from the LOG_LEVEL environment variable.
     *
     * Accepts: debug, info, warning, error, critical (case-insensitive).
     * Invalid values fall back to DEBUG.
     */
    private function resolveLogLevel(): \YasserElgammal\Green\Logging\LogLevel
    {
        $level = strtolower($this->resolveEnv('LOG_LEVEL', 'debug'));

        return \YasserElgammal\Green\Logging\LogLevel::tryFrom($level)
            ?? \YasserElgammal\Green\Logging\LogLevel::DEBUG;
    }

    /**
     * Read an environment variable with a fallback default.
     *
     * Checks $_ENV first (populated by phpdotenv), then getenv().
     */
    private function resolveEnv(string $key, string $default): string
    {
        if (!empty($_ENV[$key])) {
            return $_ENV[$key];
        }

        $value = getenv($key);
        return ($value !== false && $value !== '') ? $value : $default;
    }
}
