<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Security\Csrf;

final class CsrfConfig
{
    public readonly bool $enabled;
    public readonly int $ttl;
    public readonly int $maxTokens;
    public readonly string $sessionKey;
    public readonly string $idInput;
    public readonly string $tokenInput;
    public readonly string $idHeader;
    public readonly string $tokenHeader;
    /** @var string[] */
    public readonly array $except;

    public function __construct(array $config = [])
    {
        $this->enabled     = (bool)   ($config['enabled']      ?? true);
        $this->ttl         = (int)    ($config['ttl']          ?? 1800);
        $this->maxTokens   = (int)    ($config['max_tokens']   ?? 50);
        $this->sessionKey  = (string) ($config['session_key']  ?? '_csrf_tokens');
        $this->idInput     = (string) ($config['id_input']     ?? '_csrf_id');
        $this->tokenInput  = (string) ($config['token_input']  ?? '_csrf_token');
        $this->idHeader    = (string) ($config['id_header']    ?? 'X-CSRF-ID');
        $this->tokenHeader = (string) ($config['token_header'] ?? 'X-CSRF-TOKEN');
        $this->except      = (array)  ($config['except']       ?? []);
    }

    /**
     * Whether CSRF protection is active.
     * Callers that need a boolean check can use this or read $this->enabled directly.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    // -------------------------------------------------------------------------
    // Backward-compatible getter aliases.
    // These delegate to the public readonly properties so existing call sites
    // (tests, middleware, user code) continue to work without modification.
    // New code should read the properties directly.
    // -------------------------------------------------------------------------

    public function getTtl(): int            { return $this->ttl; }
    public function getMaxTokens(): int      { return $this->maxTokens; }
    public function getSessionKey(): string  { return $this->sessionKey; }
    public function getIdInput(): string     { return $this->idInput; }
    public function getTokenInput(): string  { return $this->tokenInput; }
    public function getIdHeader(): string    { return $this->idHeader; }
    public function getTokenHeader(): string { return $this->tokenHeader; }
    /** @return string[] */
    public function getExcept(): array       { return $this->except; }
}
