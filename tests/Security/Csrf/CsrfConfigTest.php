<?php

namespace YasserElgammal\Green\Tests\Security\Csrf;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Security\Csrf\CsrfConfig;

class CsrfConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new CsrfConfig();

        $this->assertTrue($config->isEnabled());
        $this->assertSame(1800, $config->getTtl());
        $this->assertSame(50, $config->getMaxTokens());
        $this->assertSame('_csrf_tokens', $config->getSessionKey());
        $this->assertSame('_csrf_id', $config->getIdInput());
        $this->assertSame('_csrf_token', $config->getTokenInput());
        $this->assertSame('X-CSRF-ID', $config->getIdHeader());
        $this->assertSame('X-CSRF-TOKEN', $config->getTokenHeader());
        $this->assertSame([], $config->getExcept());
    }

    public function testCustomOverrides(): void
    {
        $config = new CsrfConfig([
            'enabled'      => false,
            'ttl'          => 3600,
            'max_tokens'   => 10,
            'session_key'  => 'my_tokens',
            'id_input'     => 'my_id',
            'token_input'  => 'my_token',
            'id_header'    => 'X-MY-ID',
            'token_header' => 'X-MY-TOKEN',
            'except'       => ['/api/*'],
        ]);

        $this->assertFalse($config->isEnabled());
        $this->assertSame(3600, $config->getTtl());
        $this->assertSame(10, $config->getMaxTokens());
        $this->assertSame('my_tokens', $config->getSessionKey());
        $this->assertSame('my_id', $config->getIdInput());
        $this->assertSame('my_token', $config->getTokenInput());
        $this->assertSame('X-MY-ID', $config->getIdHeader());
        $this->assertSame('X-MY-TOKEN', $config->getTokenHeader());
        $this->assertSame(['/api/*'], $config->getExcept());
    }

    public function testPartialOverrideKeepsDefaults(): void
    {
        $config = new CsrfConfig(['ttl' => 900]);

        $this->assertTrue($config->isEnabled());
        $this->assertSame(900, $config->getTtl());
        $this->assertSame(50, $config->getMaxTokens());
    }
}
