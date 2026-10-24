<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Auth;

use Fiber;
use YasserElgammal\Green\Database\Model;
use YasserElgammal\Green\Http\Request;

final class Auth
{
    /** @var list<Request> */
    private array $requests = [];
    private int $dispatchDepth = 0;
    private ?Fiber $ownerFiber = null;

    public function beginRequest(Request $request): void
    {
        if ($this->dispatchDepth > 0 && $this->ownerFiber !== Fiber::getCurrent()) {
            throw new \LogicException('An Application cannot dispatch interleaved requests across Fibers.');
        }

        if ($this->dispatchDepth === 0) {
            $this->ownerFiber = Fiber::getCurrent();
        }

        $this->requests[] = $request;
        $this->dispatchDepth++;
    }

    public function endRequest(Request $request): void
    {
        $this->assertOwner();
        if ($this->currentRequest() !== $request) {
            throw new \LogicException('Authentication request contexts must end in reverse order.');
        }

        array_pop($this->requests);
        $this->dispatchDepth--;

        if ($this->dispatchDepth === 0) {
            $this->ownerFiber = null;
        }
    }

    public function resolve(Request $request, Authenticator $authenticator, ?string $context = null): ?object
    {
        $this->adoptRequest($request);

        $authenticatorClass = $this->contextKey($authenticator::class, $context);

        if ($request->hasResolvedUser()) {
            $resolvedBy = $request->userResolvedBy();

            if ($resolvedBy !== $authenticatorClass) {
                throw new AuthenticationException(
                    'The request identity was already resolved by another authentication method.'
                );
            }

            return $request->user();
        }

        $user = $authenticator->authenticate($request);
        $request->setUser($user, $authenticatorClass);

        return $user;
    }

    /**
     * Synchronize an intentional login/logout change with the active request.
     * Application authentication workflows are the intended caller.
     */
    public function synchronizeUser(?object $user, string $authenticatorClass, ?string $context = null): void
    {
        $this->assertOwner();
        $request = $this->currentRequest();

        if ($request !== null) {
            $source = $this->contextKey($authenticatorClass, $context);
            if ($request->hasResolvedUser() && $request->userResolvedBy() !== $source) {
                throw new AuthenticationException('Cannot synchronize a different authentication context.');
            }
            $request->setUser($user, $source);
        }
    }

    public function user(): ?object
    {
        $this->assertOwner();
        return $this->currentRequest()?->user();
    }

    public function id(): int|string|null
    {
        $user = $this->user();

        if ($user instanceof Model) {
            return $user->getPrimaryKeyValue();
        }

        if ($user !== null && isset($user->id)) {
            $id = $user->id;

            if (is_int($id) || is_string($id)) {
                return $id;
            }
        }

        return null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    private function currentRequest(): ?Request
    {
        $key = array_key_last($this->requests);

        return $key === null ? null : $this->requests[$key];
    }

    private function contextKey(string $authenticatorClass, ?string $context): string
    {
        if ($context === '') {
            throw new \InvalidArgumentException('Authentication context cannot be empty.');
        }

        return $context === null ? $authenticatorClass : $authenticatorClass . ':' . $context;
    }

    private function adoptRequest(Request $request): void
    {
        $this->assertOwner();
        $key = array_key_last($this->requests);

        if ($key === null) {
            $this->requests[] = $request;
        } elseif ($this->requests[$key] !== $request) {
            if ($this->dispatchDepth > 0) {
                throw new \LogicException('Cannot resolve authentication for a different active request.');
            }
            $this->requests[$key] = $request;
        }
    }

    private function assertOwner(): void
    {
        if ($this->dispatchDepth > 0 && $this->ownerFiber !== Fiber::getCurrent()) {
            throw new \LogicException('Authentication state belongs to another active Fiber.');
        }
    }
}
