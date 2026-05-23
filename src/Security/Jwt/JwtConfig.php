<?php

namespace YasserElgammal\Green\Security\Jwt;

class JwtConfig
{
    private const DEFAULTS = [
        'secret'    => '',
        'ttl'       => 3600,
        'algorithm' => 'HS256',
    ];

    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge(self::DEFAULTS, $config);
    }

    public function getSecret(): string
    {
        return (string) $this->config['secret'];
    }

    public function getTtl(): int
    {
        return (int) $this->config['ttl'];
    }

    public function getAlgorithm(): string
    {
        return (string) $this->config['algorithm'];
    }
}
