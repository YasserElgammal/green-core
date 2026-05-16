<?php

namespace YasserElgammal\Green\Tests\Security\Csrf;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use YasserElgammal\Green\Security\Csrf\CsrfConfig;
use YasserElgammal\Green\Security\Csrf\CsrfTokenManager;
use YasserElgammal\Green\Session\SessionManager;

class CsrfTokenManagerTest extends TestCase
{
    private SessionManager $session;
    private CsrfConfig $config;
    private CsrfTokenManager $manager;

    protected function setUp(): void
    {
        // Use MockArraySessionStorage to avoid native session issues in tests
        $symfonySession = new Session(new MockArraySessionStorage());
        $this->session  = new SessionManager($symfonySession);
        $this->config   = new CsrfConfig(['ttl' => 1800, 'max_tokens' => 5]);
        $this->manager  = new CsrfTokenManager($this->session, $this->config);

        // Start clean
        $this->session->forget($this->config->getSessionKey());
    }

    // ─── Generation ──────────────────────────────────────────

    public function testGenerateReturnsIdAndToken(): void
    {
        $pair = $this->manager->generate();

        $this->assertArrayHasKey('id', $pair);
        $this->assertArrayHasKey('token', $pair);
        $this->assertSame(32, strlen($pair['id']),    'ID should be 32 hex chars (16 bytes)');
        $this->assertSame(64, strlen($pair['token']), 'Token should be 64 hex chars (32 bytes)');
    }

    public function testGenerateCreatesUniqueTokens(): void
    {
        $a = $this->manager->generate();
        $b = $this->manager->generate();

        $this->assertNotSame($a['id'], $b['id']);
        $this->assertNotSame($a['token'], $b['token']);
    }

    public function testGenerateStoresInSession(): void
    {
        $pair   = $this->manager->generate();
        $tokens = $this->session->get($this->config->getSessionKey(), []);

        $this->assertArrayHasKey($pair['id'], $tokens);
        $this->assertSame($pair['token'], $tokens[$pair['id']]['token']);
        $this->assertArrayHasKey('expires_at', $tokens[$pair['id']]);
        $this->assertArrayHasKey('created_at', $tokens[$pair['id']]);
    }

    // ─── Validation ──────────────────────────────────────────

    public function testValidateReturnsTrueForValidToken(): void
    {
        $pair = $this->manager->generate();

        $this->assertTrue($this->manager->validate($pair['id'], $pair['token']));
    }

    public function testValidateConsumesTokenByDefault(): void
    {
        $pair = $this->manager->generate();

        $this->assertTrue($this->manager->validate($pair['id'], $pair['token']));
        // Second use must fail (one-time)
        $this->assertFalse($this->manager->validate($pair['id'], $pair['token']));
    }

    public function testValidateWithoutConsume(): void
    {
        $pair = $this->manager->generate();

        $this->assertTrue($this->manager->validate($pair['id'], $pair['token'], consume: false));
        // Token should still be valid
        $this->assertTrue($this->manager->validate($pair['id'], $pair['token'], consume: false));
    }

    public function testValidateRejectsWrongToken(): void
    {
        $pair = $this->manager->generate();

        $this->assertFalse($this->manager->validate($pair['id'], 'wrong_token_value'));
    }

    public function testValidateRejectsWrongId(): void
    {
        $pair = $this->manager->generate();

        $this->assertFalse($this->manager->validate('nonexistent_id', $pair['token']));
    }

    public function testValidateRejectsNullInputs(): void
    {
        $this->assertFalse($this->manager->validate(null, null));
        $this->assertFalse($this->manager->validate('id', null));
        $this->assertFalse($this->manager->validate(null, 'token'));
    }

    public function testValidateRejectsEmptyStrings(): void
    {
        $this->assertFalse($this->manager->validate('', ''));
    }

    // ─── Expiration ──────────────────────────────────────────

    public function testValidateRejectsExpiredToken(): void
    {
        // Use a TTL of 0 so the token expires immediately
        $config  = new CsrfConfig(['ttl' => 0]);
        $manager = new CsrfTokenManager($this->session, $config);

        $pair = $manager->generate();

        // Give it a moment to expire (ttl=0 means expires_at = time())
        sleep(1);

        $this->assertFalse($manager->validate($pair['id'], $pair['token']));
    }

    public function testCleanupExpiredRemovesOldTokens(): void
    {
        $config  = new CsrfConfig(['ttl' => 0]);
        $manager = new CsrfTokenManager($this->session, $config);

        $manager->generate();
        $manager->generate();

        sleep(1);

        $manager->cleanupExpired();

        $tokens = $this->session->get($config->getSessionKey(), []);
        $this->assertCount(0, $tokens);
    }

    // ─── Max Tokens ──────────────────────────────────────────

    public function testEnforceMaxTokensRemovesOldest(): void
    {
        // max_tokens = 5; generate 7 tokens
        for ($i = 0; $i < 7; $i++) {
            $this->manager->generate();
        }

        $tokens = $this->session->get($this->config->getSessionKey(), []);
        $this->assertCount(5, $tokens, 'Should not exceed max_tokens');
    }
}
