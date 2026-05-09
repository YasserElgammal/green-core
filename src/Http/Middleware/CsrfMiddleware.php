<?php

namespace YasserElgammal\Green\Http\Middleware;

use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Response;
use YasserElgammal\Green\Middleware\MiddlewareInterface;
use YasserElgammal\Green\Security\Csrf\CsrfConfig;
use YasserElgammal\Green\Security\Csrf\CsrfTokenManager;
use YasserElgammal\Green\Exceptions\TokenMismatchException;

class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * HTTP methods that do not require CSRF validation.
     */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    private CsrfConfig $config;
    private CsrfTokenManager $tokenManager;

    public function __construct(?CsrfConfig $config = null, ?CsrfTokenManager $tokenManager = null)
    {
        $this->config       = $config ?? new CsrfConfig();
        $this->tokenManager = $tokenManager ?? new CsrfTokenManager(session(), $this->config);
    }

    public function handle(Request $request, callable $next): Response
    {
        // Skip if CSRF protection is disabled
        if (!$this->config->isEnabled()) {
            return $next($request);
        }

        // Skip safe HTTP methods
        if (in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true)) {
            return $next($request);
        }

        // Skip excepted paths
        if ($this->isExcepted($request->getPath())) {
            return $next($request);
        }

        // Extract id and token from body, fallback to headers
        $id    = $request->input($this->config->getIdInput())
              ?? $request->header($this->config->getIdHeader());

        $token = $request->input($this->config->getTokenInput())
              ?? $request->header($this->config->getTokenHeader());

        if (!$this->tokenManager->validate($id, $token)) {
            throw new TokenMismatchException();
        }

        return $next($request);
    }

    /**
     * Check whether the given path is in the exception list.
     */
    private function isExcepted(string $path): bool
    {
        foreach ($this->config->getExcept() as $pattern) {
            if ($pattern === $path) {
                return true;
            }

            // Support trailing wildcard, e.g. "/api/*"
            if (str_ends_with($pattern, '*') && str_starts_with($path, rtrim($pattern, '*'))) {
                return true;
            }
        }

        return false;
    }
}
