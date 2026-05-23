<?php

namespace YasserElgammal\Green\Security\Jwt;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

class JwtService
{
    public function __construct(private JwtConfig $config)
    {
    }

    public function encode(array $claims): string
    {
        $issuedAt = time();
        $expire   = $issuedAt + $this->config->getTtl();

        $payload = array_merge([
            'iat' => $issuedAt,
            'exp' => $expire,
        ], $claims);

        return JWT::encode(
            $payload,
            $this->config->getSecret(),
            $this->config->getAlgorithm()
        );
    }

    public function decode(string $token): ?object
    {
        try {
            return JWT::decode(
                $token,
                new Key($this->config->getSecret(), $this->config->getAlgorithm())
            );
        } catch (Throwable) {
            return null;
        }
    }
}
