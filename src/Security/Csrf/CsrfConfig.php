<?php

namespace YasserElgammal\Green\Security\Csrf;

class CsrfConfig
{
    private const DEFAULTS = [
        'enabled'      => true,
        'ttl'          => 1800,
        'max_tokens'   => 50,
        'session_key'  => '_csrf_tokens',
        'id_input'     => '_csrf_id',
        'token_input'  => '_csrf_token',
        'id_header'    => 'X-CSRF-ID',
        'token_header' => 'X-CSRF-TOKEN',
        'except'       => [],
    ];

    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge(self::DEFAULTS, $config);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->config['enabled'];
    }

    public function getTtl(): int
    {
        return (int) $this->config['ttl'];
    }

    public function getMaxTokens(): int
    {
        return (int) $this->config['max_tokens'];
    }

    public function getSessionKey(): string
    {
        return $this->config['session_key'];
    }

    public function getIdInput(): string
    {
        return $this->config['id_input'];
    }

    public function getTokenInput(): string
    {
        return $this->config['token_input'];
    }

    public function getIdHeader(): string
    {
        return $this->config['id_header'];
    }

    public function getTokenHeader(): string
    {
        return $this->config['token_header'];
    }

    /**
     * @return string[]
     */
    public function getExcept(): array
    {
        return (array) $this->config['except'];
    }
}
