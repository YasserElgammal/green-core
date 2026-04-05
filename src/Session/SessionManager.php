<?php

namespace YasserElgammal\Green\Session;

use Symfony\Component\HttpFoundation\Session\Session;

class SessionManager
{
    protected Session $session;

    public function __construct(?Session $session = null)
    {
        $this->session = $session ?? new Session();

        if (!$this->session->isStarted()) {
            $this->session->start();
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->session->get($key, $default);
    }

    public function put(string $key, mixed $value): void
    {
        $this->session->set($key, $value);
    }

    public function has(string $key): bool
    {
        return $this->session->has($key);
    }

    public function forget(string $key): void
    {
        $this->session->remove($key);
    }

    public function flush(): void
    {
        $this->session->clear();
    }

    public function flash(string $key, mixed $value): void
    {
        $this->session->getFlashBag()->set($key, $value); // Replaces existing by key
    }

    public function getFlash(string $key): array
    {
        return $this->session->getFlashBag()->get($key, []);
    }

    public function regenerateId(bool $destroy = false): bool
    {
        return $this->session->migrate($destroy);
    }

    public function getId(): string
    {
        return $this->session->getId();
    }

    public function getSymfonySession(): Session
    {
        return $this->session;
    }
}
