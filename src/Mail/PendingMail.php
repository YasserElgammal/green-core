<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Mail;

use Closure;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use YasserElgammal\Green\Config\Typed\MailConfig;
use YasserElgammal\Green\View\View;

class PendingMail
{
    private ?Address $from = null;

    /** @var array<int, Address> */
    private array $to = [];

    /** @var array<int, Address> */
    private array $cc = [];

    /** @var array<int, Address> */
    private array $bcc = [];

    /** @var array<int, Address> */
    private array $replyTo = [];

    private ?string $subject = null;
    private ?string $html = null;
    private ?string $text = null;

    /** @var array<int, array{type: string, path?: string, data?: string, name: ?string, contentType: ?string}> */
    private array $attachments = [];

    /** @var array<int, Closure(Email): void> */
    private array $callbacks = [];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ?MailConfig $config = null,
    ) {
    }

    /**
     * @param string|array<int|string, string|Address>|Address $address
     */
    public function to(string|array|Address $address, ?string $name = null): self
    {
        $this->to = array_merge($this->to, $this->normalizeAddresses($address, $name));
        return $this;
    }

    public function from(string|Address $address, ?string $name = null): self
    {
        $this->from = is_string($address)
            ? new Address($address, $name ?? '')
            : $address;

        return $this;
    }

    /**
     * @param string|array<int|string, string|Address>|Address $address
     */
    public function cc(string|array|Address $address, ?string $name = null): self
    {
        $this->cc = array_merge($this->cc, $this->normalizeAddresses($address, $name));
        return $this;
    }

    /**
     * @param string|array<int|string, string|Address>|Address $address
     */
    public function bcc(string|array|Address $address, ?string $name = null): self
    {
        $this->bcc = array_merge($this->bcc, $this->normalizeAddresses($address, $name));
        return $this;
    }

    public function replyTo(string|Address $address, ?string $name = null): self
    {
        $this->replyTo = array_merge($this->replyTo, $this->normalizeAddresses($address, $name));
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Render the given Twig template as the HTML body.
     *
     * @param string $template Twig template name (e.g. 'emails/welcome')
     * @param array<string, mixed> $data Variables passed to the template
     */
    public function view(string $template, array $data = []): self
    {
        $this->html = View::render($template, $data);
        return $this;
    }

    /**
     * Render the given Twig template as the plain text alternative.
     *
     * @param string $template Twig template name
     * @param array<string, mixed> $data Variables passed to the template
     */
    public function textView(string $template, array $data = []): self
    {
        $this->text = View::render($template, $data);
        return $this;
    }

    public function html(string $html): self
    {
        $this->html = $html;
        return $this;
    }

    public function text(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function attach(string $path, ?string $name = null, ?string $contentType = null): self
    {
        $this->attachments[] = [
            'type' => 'path',
            'path' => $path,
            'name' => $name,
            'contentType' => $contentType,
        ];
        return $this;
    }

    public function attachData(string $data, string $name, ?string $contentType = null): self
    {
        $this->attachments[] = [
            'type' => 'data',
            'data' => $data,
            'name' => $name,
            'contentType' => $contentType,
        ];
        return $this;
    }

    /**
     * Hook into the underlying Symfony Email object before sending.
     *
     * @param Closure(Email): void|callable(Email): void $callback
     */
    public function with(callable $callback): self
    {
        $this->callbacks[] = Closure::fromCallable($callback);
        return $this;
    }

    public function build(): Email
    {
        $email = new Email();

        $from = $this->from;
        if ($from === null && $this->config !== null) {
            $from = new Address($this->config->fromAddress, $this->config->fromName);
        }

        if ($from !== null) {
            $email->from($from);
        }

        foreach ($this->to as $to) {
            $email->addTo($to);
        }

        foreach ($this->cc as $cc) {
            $email->addCc($cc);
        }

        foreach ($this->bcc as $bcc) {
            $email->addBcc($bcc);
        }

        foreach ($this->replyTo as $replyTo) {
            $email->addReplyTo($replyTo);
        }

        if ($this->subject !== null) {
            $email->subject($this->subject);
        }

        if ($this->html !== null) {
            $email->html($this->html);
        }

        if ($this->text !== null) {
            $email->text($this->text);
        }

        foreach ($this->attachments as $attachment) {
            if ($attachment['type'] === 'path' && isset($attachment['path'])) {
                $email->attachFromPath(
                    $attachment['path'],
                    $attachment['name'],
                    $attachment['contentType']
                );
            } elseif ($attachment['type'] === 'data' && isset($attachment['data'], $attachment['name'])) {
                $email->attach(
                    $attachment['data'],
                    $attachment['name'],
                    $attachment['contentType']
                );
            }
        }

        foreach ($this->callbacks as $callback) {
            $callback($email);
        }

        return $email;
    }

    public function send(): void
    {
        $this->mailer->send($this->build());
    }

    /**
     * @param string|array<int|string, string|Address>|Address $addresses
     * @return array<int, Address>
     */
    private function normalizeAddresses(string|array|Address $addresses, ?string $name = null): array
    {
        if ($addresses instanceof Address) {
            return [$addresses];
        }

        if (is_string($addresses)) {
            return [new Address($addresses, $name ?? '')];
        }

        $normalized = [];
        foreach ($addresses as $key => $value) {
            if ($value instanceof Address) {
                $normalized[] = $value;
            } elseif (is_string($key)) {
                $normalized[] = new Address($key, (string) $value);
            } elseif (is_string($value)) {
                $normalized[] = new Address($value);
            }
        }

        return $normalized;
    }
}
