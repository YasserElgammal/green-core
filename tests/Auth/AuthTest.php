<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Auth;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Auth\Auth;
use YasserElgammal\Green\Auth\Authenticator;
use YasserElgammal\Green\Database\Model;
use YasserElgammal\Green\Http\Request;
use YasserElgammal\Green\Http\Payload;
use YasserElgammal\Green\Auth\AuthenticationException;

final class AuthTest extends TestCase
{
    public function testAnonymousIdentityIsCachedInAnExplicitContext(): void
    {
        $auth = new Auth();
        $request = new Request();
        $customAdapter = new CountingAuthenticator(null);
        self::assertNull($auth->resolve($request, $customAdapter, 'green-key'));
        self::assertNull($auth->resolve($request, $customAdapter, 'green-key'));
        self::assertSame(1, $customAdapter->calls);
        $this->expectException(AuthenticationException::class);
        $auth->resolve($request, $customAdapter, 'oauth-resource-server');
    }

    public function testSynchronizationCannotOverwriteAnotherContext(): void
    {
        $auth = new Auth();
        $request = new Request();
        $auth->resolve($request, new CountingAuthenticator((object) ['id' => 1]), 'web');
        try {
            $auth->synchronizeUser((object) ['id' => 2], CountingAuthenticator::class, 'admin');
            self::fail('A different context must not replace the current actor.');
        } catch (AuthenticationException) {
            self::assertSame(1, $auth->id());
        }
    }

    public function testSynchronizationIsVisibleThroughAnExistingPayload(): void
    {
        $auth = new Auth();
        $request = new Request();
        $auth->beginRequest($request);
        $auth->resolve($request, new CountingAuthenticator(null), 'web');
        $payload = new EmptyPayload($request);
        $user = (object) ['id' => 4];
        $auth->synchronizeUser($user, CountingAuthenticator::class, 'web');
        self::assertSame($user, $payload->user());
        $auth->synchronizeUser(null, CountingAuthenticator::class, 'web');
        self::assertNull($payload->user());
        $auth->endRequest($request);
        self::assertNull($auth->user());
    }

    public function testItExposesTheSameResolvedIdentityThroughAuthAndRequest(): void
    {
        $identity = new class(['id' => 42]) extends Model {};
        $authenticator = new CountingAuthenticator($identity);
        $request = new Request();
        $auth = new Auth();

        self::assertSame($identity, $auth->resolve($request, $authenticator));
        self::assertSame($identity, $auth->resolve($request, $authenticator));
        self::assertSame($identity, $auth->user());
        self::assertSame($identity, $request->user());
        self::assertSame(42, $auth->id());
        self::assertTrue($auth->check());
        self::assertFalse($auth->guest());
        self::assertSame(1, $authenticator->calls);
    }

    public function testAnonymousAndConsecutiveRequestsRemainIsolated(): void
    {
        $auth = new Auth();
        $first = new Request();
        $second = new Request();

        $auth->resolve($first, new CountingAuthenticator((object) ['id' => 'first']));
        self::assertSame('first', $auth->id());

        $auth->resolve($second, new CountingAuthenticator(null));
        self::assertNull($auth->user());
        self::assertNull($auth->id());
        self::assertFalse($auth->check());
        self::assertTrue($auth->guest());
        self::assertNotSame($first->user(), $second->user());
    }

    public function testNestedRequestContextRestoresTheOuterIdentity(): void
    {
        $auth = new Auth();
        $outer = new Request();
        $inner = new Request();
        $outerIdentity = (object) ['id' => 'outer'];
        $innerIdentity = (object) ['id' => 'inner'];

        $auth->beginRequest($outer);
        $auth->resolve($outer, new CountingAuthenticator($outerIdentity));
        $auth->beginRequest($inner);
        $auth->resolve($inner, new CountingAuthenticator($innerIdentity));

        self::assertSame($innerIdentity, $auth->user());

        $auth->endRequest($inner);
        self::assertSame($outerIdentity, $auth->user());

        $auth->endRequest($outer);
        self::assertNull($auth->user());
    }

    public function testConflictingAuthenticatorsCannotReplaceAResolvedIdentity(): void
    {
        $auth = new Auth();
        $request = new Request();
        $auth->resolve($request, new FirstAuthenticator((object) ['id' => 1]));

        $this->expectException(AuthenticationException::class);
        $auth->resolve($request, new SecondAuthenticator((object) ['id' => 2]));
    }

    public function testAuthenticationExceptionLeavesTheRequestUnresolved(): void
    {
        $auth = new Auth();
        $request = new Request();

        try {
            $auth->resolve($request, new ThrowingAuthenticator());
            self::fail('The authenticator should throw.');
        } catch (\RuntimeException) {
            self::assertFalse($request->hasResolvedUser());
        }

        $identity = (object) ['id' => 10];
        self::assertSame($identity, $auth->resolve($request, new CountingAuthenticator($identity)));
    }

    public function testPayloadSharesRatherThanCopiesRequestIdentityState(): void
    {
        $request = new Request();
        $payload = new EmptyPayload($request);
        $identity = (object) ['id' => 7];

        $request->setUser($identity);

        self::assertTrue($payload->hasResolvedUser());
        self::assertSame($identity, $payload->user());
        self::assertSame($identity, $payload->getAttribute('user'));

        $payload->setUser(null);

        self::assertNull($request->user());
    }

    public function testInterleavedFiberCannotObserveOrReplaceAnotherRequest(): void
    {
        $auth = new Auth();
        $identity = (object) ['id' => 'first'];
        $first = new \Fiber(function () use ($auth, $identity): void {
            $request = new Request();
            $auth->beginRequest($request);
            try {
                $auth->resolve($request, new CountingAuthenticator($identity));
                \Fiber::suspend();
                self::assertSame($identity, $auth->user());
            } finally {
                $auth->endRequest($request);
            }
        });

        $first->start();

        try {
            $auth->user();
            self::fail('Another Fiber must not observe the identity.');
        } catch (\LogicException) {
            self::assertTrue(true);
        }

        try {
            $auth->beginRequest(new Request());
            self::fail('Another Fiber must not replace the request.');
        } catch (\LogicException) {
            self::assertTrue(true);
        }

        $first->resume();
        self::assertNull($auth->user());
    }
}

class CountingAuthenticator implements Authenticator
{
    public int $calls = 0;

    public function __construct(private readonly ?object $identity)
    {
    }

    public function authenticate(Request $request): ?object
    {
        $this->calls++;

        return $this->identity;
    }
}

final class FirstAuthenticator extends CountingAuthenticator
{
}

final class SecondAuthenticator extends CountingAuthenticator
{
}

final class ThrowingAuthenticator implements Authenticator
{
    public function authenticate(Request $request): ?object
    {
        throw new \RuntimeException('authentication failed');
    }
}

final class EmptyPayload extends Payload
{
    public function rules(): array
    {
        return [];
    }
}
