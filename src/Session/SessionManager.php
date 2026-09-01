<?php

namespace YasserElgammal\Green\Session;

use Symfony\Component\HttpFoundation\Session\Session;

class SessionManager
{
    protected Session $session;

    public function __construct(?Session $session = null)
    {
        $this->session = $session ?? new Session();
    }

    public function start(): bool
    {
        if ($this->session->isStarted()) {
            return true;
        }

        return $this->session->start();
    }

    public function isStarted(): bool
    {
        return $this->session->isStarted();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->startIfNeeded();

        return $this->session->get($key, $default);
    }

    public function put(string $key, mixed $value): void
    {
        $this->startIfNeeded();

        $this->session->set($key, $value);
    }

    public function has(string $key): bool
    {
        $this->startIfNeeded();

        return $this->session->has($key);
    }

    public function forget(string $key): void
    {
        $this->startIfNeeded();

        $this->session->remove($key);
    }

    public function flush(): void
    {
        $this->startIfNeeded();

        $this->session->clear();
    }

    public function flash(string $key, mixed $value): void
    {
        $this->startIfNeeded();

        $this->session->getFlashBag()->set($key, $value); // Replaces existing by key
    }

    public function getFlash(string $key): array
    {
        $this->startIfNeeded();

        return $this->session->getFlashBag()->get($key, []);
    }

    public function regenerateId(bool $destroy = false): bool
    {
        $this->startIfNeeded();

        return $this->session->migrate($destroy);
    }

    public function invalidate(): bool
    {
        $this->startIfNeeded();

        return $this->session->invalidate();
    }

    public function getId(): string
    {
        return $this->session->getId();
    }

    public function getSymfonySession(): Session
    {
        return $this->session;
    }

    private function startIfNeeded(): void
    {
        if (!$this->session->isStarted()) {
            $this->session->start();
        }
    }
}
