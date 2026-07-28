<?php

namespace YasserElgammal\Green\Exceptions;

use Throwable;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Http\JsonResponse;
use YasserElgammal\Green\Http\ValidationException;
use YasserElgammal\Green\Http\HttpExceptionInterface;
use YasserElgammal\Green\View\View;
use YasserElgammal\Green\ErrorHandling\ErrorRecord;
use YasserElgammal\Green\ErrorHandling\RequestContext;
use YasserElgammal\Green\Logging\LogManager;
use YasserElgammal\Green\Config\Typed\ApplicationConfig;

class ExceptionHandler
{
    /**
     * @param LogManager|null $logManager  Injected by Application — nullable for backward compatibility
     */
    public function __construct(
        private ?LogManager $logManager = null,
        private ?ApplicationConfig $config = null,
    ) {
    }

    public function handle(Throwable $e, Request $request): Response
    {
        $record = null;

        // Log the error through the injected LogManager (dedup prevents double-logging
        // if the error was already captured by GreenErrorKernel's global handler)
        if ($this->logManager !== null) {
            try {
                $context = RequestContext::capture();
                $record = ErrorRecord::fromException($e, $context);
                $this->logManager->log($record);
            } catch (\Throwable) {
                // Logging must never break error rendering
            }
        }

        $isDebug = $this->isDebug();
        $expectsJson = $this->expectsJson($request);

        $traceId = $record?->id ?? $this->generateTraceId();
        $statusCode = match (true) {
            $e instanceof HttpExceptionInterface => $e->getStatusCode(),
            $e instanceof ValidationException => 422,
            default => 500,
        };
        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

        if ($expectsJson) {
            $response = $this->renderJson($e, $statusCode, $isDebug, $traceId);
        } else {
            $response = $this->renderHtml($e, $statusCode, $isDebug, $traceId);
        }

        foreach ($headers as $name => $value) {
            $response->setHeader($name, $value);
        }

        return $response;
    }

    protected function renderJson(Throwable $e, int $statusCode, bool $isDebug, string $traceId): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return new JsonResponse([
                'error' => 'Validation error',
                'errors' => $e->getErrors(),
                'trace_id' => $traceId,
            ], $statusCode);
        }

        $response = [
            'error' => $this->getErrorTitle($statusCode),
            'message' => $isDebug ? $e->getMessage() : 'An unexpected error occurred.',
            'trace_id' => $traceId,
        ];

        if ($isDebug) {
            $response['file'] = $e->getFile();
            $response['line'] = $e->getLine();
            $response['trace'] = $e->getTrace();
        }

        return new JsonResponse($response, $statusCode);
    }

    protected function renderHtml(Throwable $e, int $statusCode, bool $isDebug, string $traceId): Response
    {
        $viewParameters = [
            'title' => $this->getErrorTitle($statusCode),
            'message' => $isDebug ? $e->getMessage() : 'An unexpected error occurred.',
            'debug' => $isDebug,
            'status_code' => $statusCode,
            'trace_id' => $traceId,
        ];

        if ($isDebug) {
            $viewParameters['exception'] = $e;
            $viewParameters['file'] = $e->getFile();
            $viewParameters['line'] = $e->getLine();
            $viewParameters['trace'] = $e->getTraceAsString();
        }

        // Check if there is a specific view for the error code, e.g., 404.twig, 500.twig, otherwise fallback to oops.twig
        $viewName = "errors/{$statusCode}";
        $templatePath = dirname(__DIR__, 2) . "/views/errors/{$statusCode}.twig";
        
        if (!file_exists($templatePath)) {
            $viewName = "errors/oops";
        }

        if ($e instanceof ValidationException && $isDebug === false) {
             // For non-JSON validation errors outside debug, we usually don't reach here 
             // without previous redirect/session flashing. We'll simply show a generic oops page or specific 422.
             $viewParameters['message'] = "There was a validation issue with your submission.";
        }

        try {
            $content = View::render($viewName, $viewParameters);
        } catch (\Throwable $viewError) {
            // View rendering failed, fallback to plain text so we don't end up in an infinite loop
            $content = "Oops! Something went wrong.\n\n" . ($isDebug ? "Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() : "");
            return new Response($content, $statusCode, ['Content-Type' => 'text/plain']);
        }

        return new Response($content, $statusCode);
    }

    protected function expectsJson(Request $request): bool
    {
        if ($request->wantsJson() || $request->isJson()) {
            return true;
        }

        $path = $request->getPath();
        if (str_starts_with($path, '/api')) {
            return true;
        }

        return false;
    }

    protected function isDebug(): bool
    {
        return $this->config?->debug ?? false;
    }

    private function generateTraceId(): string
    {
        try {
            return 'ERR_' . bin2hex(random_bytes(12));
        } catch (\Throwable) {
            return 'ERR_' . str_replace('.', '', uniqid('', true));
        }
    }

    public function cleanMessage(string $message): string
    {
        // General scrubbing of technical jargon
        if (stripos($message, 'Unknown column') !== false || stripos($message, 'Syntax error') !== false || stripos($message, 'Base table or view not found') !== false) {
            return 'Database structure issue';
        }
        
        if (stripos($message, 'Connection refused') !== false || stripos($message, 'Access denied for user') !== false) {
            return 'Database connection failed';
        }

        return 'Unexpected error occurred';
    }

    protected function getErrorTitle(int $statusCode): string
    {
        $titles = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Page Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Server Error',
            503 => 'Service Unavailable',
        ];

        return $titles[$statusCode] ?? 'Whoops, looks like something went wrong.';
    }
}
