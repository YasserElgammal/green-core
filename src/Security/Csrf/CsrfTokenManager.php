<?php

namespace YasserElgammal\Green\Security\Csrf;

use YasserElgammal\Green\Session\SessionManager;

class CsrfTokenManager
{
    private SessionManager $session;
    private CsrfConfig $config;

    public function __construct(SessionManager $session, CsrfConfig $config)
    {
        $this->session = $session;
        $this->config  = $config;
    }

    /**
     * Generate a new CSRF token pair.
     *
     * Automatically cleans up expired tokens and enforces the
     * maximum active-tokens limit before returning.
     *
     * @return array{id: string, token: string}
     */
    public function generate(): array
    {
        $this->cleanupExpired();

        $id    = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        $now   = time();

        $tokens       = $this->getTokens();
        $tokens[$id]  = [
            'token'      => $token,
            'expires_at' => $now + $this->config->getTtl(),
            'created_at' => $now,
        ];

        $this->saveTokens($tokens);
        $this->enforceMaxTokens();

        return [
            'id'    => $id,
            'token' => $token,
        ];
    }

    /**
     * Validate a submitted CSRF token.
     *
     * @param string|null $id      The token identifier.
     * @param string|null $token   The token value.
     * @param bool        $consume Whether to remove the token after successful validation.
     *
     * @return bool
     */
    public function validate(?string $id, ?string $token, bool $consume = true): bool
    {
        if ($id === null || $token === null || $id === '' || $token === '') {
            return false;
        }

        $tokens = $this->getTokens();

        if (!isset($tokens[$id])) {
            return false;
        }

        $stored = $tokens[$id];

        // Check expiration
        if ($stored['expires_at'] < time()) {
            unset($tokens[$id]);
            $this->saveTokens($tokens);
            return false;
        }

        // Timing-safe comparison
        if (!hash_equals($stored['token'], $token)) {
            return false;
        }

        // Consume token (one-time use)
        if ($consume) {
            unset($tokens[$id]);
            $this->saveTokens($tokens);
        }

        return true;
    }

    /**
     * Remove all expired tokens from the session.
     */
    public function cleanupExpired(): void
    {
        $tokens = $this->getTokens();
        $now    = time();

        $tokens = array_filter($tokens, fn(array $entry) => $entry['expires_at'] >= $now);

        $this->saveTokens($tokens);
    }

    /**
     * Enforce the maximum number of active tokens.
     *
     * If the count exceeds max_tokens, the oldest tokens (by created_at) are removed.
     */
    private function enforceMaxTokens(): void
    {
        $tokens = $this->getTokens();
        $max    = $this->config->getMaxTokens();

        if (count($tokens) <= $max) {
            return;
        }

        // Sort ascending by created_at so oldest come first
        uasort($tokens, fn(array $a, array $b) => $a['created_at'] <=> $b['created_at']);

        // Keep only the newest $max tokens
        $tokens = array_slice($tokens, count($tokens) - $max, $max, true);

        $this->saveTokens($tokens);
    }

    /**
     * @return array<string, array{token: string, expires_at: int, created_at: int}>
     */
    private function getTokens(): array
    {
        return $this->session->get($this->config->getSessionKey(), []);
    }

    private function saveTokens(array $tokens): void
    {
        $this->session->put($this->config->getSessionKey(), $tokens);
    }
}
