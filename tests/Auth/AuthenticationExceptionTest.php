<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Auth;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Auth\AuthenticationException;
use YasserElgammal\Green\Http\HttpExceptionInterface;

final class AuthenticationExceptionTest extends TestCase
{
    public function testItIsAnHttp401DistinctFromAuthorizationFailure(): void
    {
        $exception = new AuthenticationException();

        self::assertInstanceOf(HttpExceptionInterface::class, $exception);
        self::assertSame(401, $exception->getStatusCode());
        self::assertSame([], $exception->getHeaders());
    }
}
