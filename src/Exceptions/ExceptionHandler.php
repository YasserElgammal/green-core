<?php

namespace YasserElgammal\Green\Exceptions;

use Throwable;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Http\JsonResponse;
use YasserElgammal\Green\Http\ValidationException;
use YasserElgammal\Green\View\View;

class ExceptionHandler
{
    public function handle(Throwable $e, Request $request): Response
    {
        $isDebug = $this->isDebug();
        $expectsJson = $this->expectsJson($request);

        // Map specifics classes to codes
        $statusCode = 500;
        if ($e instanceof ValidationException) {
            $statusCode = 422;
        } elseif ($e->getCode() >= 400 && $e->getCode() < 600) {
            $statusCode = $e->getCode();
        }

        if ($expectsJson) {
            return $this->renderJson($e, $statusCode, $isDebug);
        }

        return $this->renderHtml($e, $statusCode, $isDebug);
    }

    protected function renderJson(Throwable $e, int $statusCode, bool $isDebug): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return new JsonResponse([
                'error' => 'Validation error',
                'errors' => $e->getErrors(),
            ], $statusCode);
        }

        $response = [
            'error' => $this->getErrorTitle($statusCode),
            'message' => $isDebug ? $e->getMessage() : $this->cleanMessage($e->getMessage()),
        ];

        if ($isDebug) {
            $response['file'] = $e->getFile();
            $response['line'] = $e->getLine();
            $response['trace'] = $e->getTrace();
            // Using uniqid or a better method for a trace ID, useful for logs even if we don't log yet
            $response['trace_id'] = uniqid('ERR_');
        }

        return new JsonResponse($response, $statusCode);
    }

    protected function renderHtml(Throwable $e, int $statusCode, bool $isDebug): Response
    {
        $viewParameters = [
            'title' => $this->getErrorTitle($statusCode),
            'message' => $isDebug ? $e->getMessage() : $this->cleanMessage($e->getMessage()),
            'debug' => $isDebug,
            'status_code' => $statusCode,
            'trace_id' => uniqid('ERR_'),
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
        } catch (\Exception $viewError) {
            // View rendering failed, fallback to plain text so we don't end up in an infinite loop
            $content = "Oops! Something went wrong.\n\n" . ($isDebug ? "Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() : "");
            return new Response($content, $statusCode, ['Content-Type' => 'text/plain']);
        }

        return new Response($content, $statusCode);
    }

    protected function expectsJson(Request $request): bool
    {
        $accept = $request->header('Accept', '');
        if (str_contains($accept, 'application/json')) {
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
        $debug = $_ENV['APP_DEBUG'] ?? false;
        
        if (is_string($debug)) {
            return strtolower($debug) === 'true' || $debug === '1';
        }
        
        return (bool) $debug;
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
