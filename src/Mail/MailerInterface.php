<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Mail;

use Closure;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use YasserElgammal\Green\Mail\Transports\ArrayTransport;

interface MailerInterface
{
    /**
     * Begin a fluent pending mail builder with recipient.
     *
     * @param string|array<int|string, string|Address>|Address $address
     */
    public function to(string|array|Address $address, ?string $name = null): PendingMail;

    /**
     * Begin a fluent pending mail builder with sender.
     */
    public function from(string|Address $address, ?string $name = null): PendingMail;

    /**
     * Begin a fluent pending mail builder with a rendered Twig template.
     *
     * @param array<string, mixed> $data
     */
    public function view(string $template, array $data = []): PendingMail;

    /**
     * Begin a fluent pending mail builder with raw HTML body.
     */
    public function html(string $html): PendingMail;

    /**
     * Begin a fluent pending mail builder with plain text body.
     */
    public function text(string $text): PendingMail;

    /**
     * Send an email immediately.
     */
    public function send(Email $email): void;

    /**
     * Send a raw text email configured by a callback.
     *
     * @param Closure(Email): void|callable(Email): void $callback
     */
    public function raw(string $text, Closure|callable $callback): void;

    /**
     * Swap the active transport with an in-memory transport for testing.
     */
    public function fake(): ArrayTransport;

    /**
     * Get the active transport instance.
     */
    public function getTransport(): TransportInterface;

    /**
     * Set the active transport instance.
     */
    public function setTransport(TransportInterface $transport): static;
}
