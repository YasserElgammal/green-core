<?php

namespace YasserElgammal\Green\ErrorHandling;

use Throwable;
use YasserElgammal\Green\Logging\LogManager;

/**
 * Central error orchestrator — registers all PHP-level handlers and
 * routes every error through a single unified pipeline.
 *
 * Captures:
 *  - Uncaught exceptions       → set_exception_handler
 *  - PHP warnings/notices      → set_error_handler
 *  - Fatal errors              → register_shutdown_function + error_get_last()
 *
 * Safety:
 *  - Loop prevention via $isHandling flag
 *  - All logging wrapped in try/catch (logging never breaks the app)
 *  - Respects @ operator via error_reporting() check
 */
final class GreenErrorKernel
{
    /**
     * Loop prevention flag — true while the kernel is actively handling
     * an error. Prevents infinite recursion if the logging layer itself throws.
     */
    private bool $isHandling = false;

    /**
     * Previous exception handler, restored if the kernel is unregistered.
     * @var callable|null
     */
    private mixed $previousExceptionHandler = null;

    /**
     * Previous error handler.
     * @var callable|null
     */
    private mixed $previousErrorHandler = null;

    public function __construct(
        private readonly LogManager $logManager,
    ) {
    }

    /**
     * Get the LogManager instance used by this kernel.
     */
    public function getLogManager(): LogManager
    {
        return $this->logManager;
    }

    /**
     * Register all PHP-level error handlers.
     *
     * Call this once during application bootstrap (typically in Application::__construct).
     * After this call, ALL errors are automatically captured.
     */
    public function register(): void
    {
        // Store previous handlers so we can chain them if needed
        $this->previousExceptionHandler = set_exception_handler([$this, 'handleException']);
        $this->previousErrorHandler     = set_error_handler([$this, 'handlePhpError']);

        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Handle an uncaught exception.
     *
     * This is called by PHP's set_exception_handler. The exception has
     * already escaped all try/catch blocks — this is the last line of defense.
     */
    public function handleException(Throwable $e): void
    {
        $context = RequestContext::capture();
        $record  = ErrorRecord::fromException($e, $context);

        $this->handleError($record);

        // Chain to previous handler if one existed
        if ($this->previousExceptionHandler !== null) {
            call_user_func($this->previousExceptionHandler, $e);
        }
    }

    /**
     * Handle a PHP error (warning, notice, deprecation, etc.).
     *
     * Returning false lets PHP handle the error normally (display/suppress
     * according to error_reporting). Returning true suppresses it.
     *
     * @return bool  Always returns false to allow normal PHP error handling
     */
    public function handlePhpError(int $severity, string $message, string $file, int $line): bool
    {
        // Respect the @ operator: when @ is used, error_reporting() returns 0
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $context = RequestContext::capture();
        $record  = ErrorRecord::fromPhpError($severity, $message, $file, $line, $context);

        $this->handleError($record);

        // Chain to previous handler if one existed
        if ($this->previousErrorHandler !== null) {
            return call_user_func($this->previousErrorHandler, $severity, $message, $file, $line);
        }

        // Return false to let PHP's default error handling continue
        return false;
    }

    /**
     * Handle a fatal error on shutdown.
     *
     * Called by register_shutdown_function. Checks error_get_last() for
     * fatal errors that couldn't be caught by set_error_handler.
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null) {
            return;
        }

        // Only handle truly fatal error types
        $fatalTypes = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE;

        if (!($error['type'] & $fatalTypes)) {
            return;
        }

        $context = RequestContext::capture();
        $record  = ErrorRecord::fromFatalError($error, $context);

        $this->handleError($record);
    }

    /**
     * THE unified entry point — all errors flow through here.
     *
     * 1. Checks the loop prevention flag
     * 2. Delegates to LogManager
     * 3. Resets the flag in a finally block (guaranteed cleanup)
     */
    private function handleError(ErrorRecord $record): void
    {
        // Loop prevention: if we're already handling an error, bail out.
        // This prevents infinite recursion if the logger itself triggers an error.
        if ($this->isHandling) {
            // Last resort: write to PHP's built-in error log
            error_log(sprintf(
                '[Green ErrorKernel] Recursive error detected — %s in %s:%d',
                $record->message,
                $record->file,
                $record->line
            ));
            return;
        }

        $this->isHandling = true;

        try {
            $this->logManager->log($record);
        } catch (\Throwable $e) {
            // Logging must NEVER break the application.
            // Last-resort fallback to PHP's native error_log.
            error_log(sprintf(
                '[Green ErrorKernel] Logging failed: %s | Original error: %s in %s:%d',
                $e->getMessage(),
                $record->message,
                $record->file,
                $record->line
            ));
        } finally {
            $this->isHandling = false;
        }
    }
}
