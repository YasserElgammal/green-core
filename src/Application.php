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
use YasserElgammal\Green\Drive\DriveManager;
use YasserElgammal\Green\Drive\Drive;

class Application
{
    private Drive $drive;
    public Router $router;

    private GreenErrorKernel $errorKernel;

    private LogManager $logManager;

    public function __construct()
    {
        $this->bootErrorHandling();
        $this->bootDrive();
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
     */
    private function bootErrorHandling(): void
    {
        $logDir = $this->resolveLogDirectory();

        $this->logManager = new LogManager();
        $this->logManager->addDriver(new FileLogger($logDir));

        // Register the LogManager with the green_log() helper function
        green_log_set_manager($this->logManager);

        $this->errorKernel = new GreenErrorKernel($this->logManager);
        $this->errorKernel->register();
    }

    /**
     * Resolve the log directory path.
     *
     * Defaults to {project-root}/storage/logs. Can be overridden
     * via the LOG_DIR environment variable.
     */
    private function resolveLogDirectory(): string
    {
        if (!empty($_ENV['LOG_DIR'])) {
            return $_ENV['LOG_DIR'];
        }

        // Go up from src/ to project root, then into storage/logs
        return dirname(__DIR__) . '/storage/logs';
    }
    /**
     * Bootstrap the Drive file storage system.
     *
     * Loads the drive configuration from config/drive.php (path
     * configurable via DRIVE_CONFIG env variable), creates the
     * DriveManager and Drive instances, and registers the global
     * drive() helper.
     */
    private function bootDrive(): void
    {
        $configFile = $this->resolveEnv('DRIVE_CONFIG', 'config/drive.php');

        if (!str_starts_with($configFile, '/') && !preg_match('/^[A-Za-z]:[\\\\\/]/', $configFile)) {
            $basePath = defined('BASE_PATH') ? rtrim(BASE_PATH, '/\\') : getcwd();
            $configFile = $basePath . DIRECTORY_SEPARATOR . ltrim($configFile, '/\\');
        }

        $config = [];
        if (file_exists($configFile)) {
            $config = require $configFile;
        }

        $manager     = new DriveManager($config);
        $this->drive = new Drive($manager);

        // Register with the global drive() helper function
        drive_set_instance($this->drive);
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
