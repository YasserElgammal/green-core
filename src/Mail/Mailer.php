<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Mail;

use Closure;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use YasserElgammal\Green\Config\Typed\MailConfig;
use YasserElgammal\Green\Mail\Transports\ArrayTransport;

class Mailer implements MailerInterface
{
    private SymfonyMailer $symfonyMailer;
    private TransportInterface $transport;

    public function __construct(
        private readonly MailConfig $config,
        ?TransportInterface $transport = null,
    ) {
        $this->transport = $transport ?? $this->createDefaultTransport($config);
        $this->symfonyMailer = new SymfonyMailer($this->transport);
    }

    /**
     * @param string|array<int|string, string|Address>|Address $address
     */
    public function to(string|array|Address $address, ?string $name = null): PendingMail
    {
        return (new PendingMail($this, $this->config))->to($address, $name);
    }

    public function from(string|Address $address, ?string $name = null): PendingMail
    {
        return (new PendingMail($this, $this->config))->from($address, $name);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function view(string $template, array $data = []): PendingMail
    {
        return (new PendingMail($this, $this->config))->view($template, $data);
    }

    public function html(string $html): PendingMail
    {
        return (new PendingMail($this, $this->config))->html($html);
    }

    public function text(string $text): PendingMail
    {
        return (new PendingMail($this, $this->config))->text($text);
    }

    public function send(Email $email): void
    {
        if (empty($email->getFrom())) {
            $email->from(new Address($this->config->fromAddress, $this->config->fromName));
        }

        $this->symfonyMailer->send($email);
    }

    public function raw(string $text, Closure|callable $callback): void
    {
        $email = (new Email())->text($text);
        $callback($email);
        $this->send($email);
    }

    public function fake(): ArrayTransport
    {
        $fake = new ArrayTransport();
        $this->setTransport($fake);
        return $fake;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    public function setTransport(TransportInterface $transport): static
    {
        $this->transport = $transport;
        $this->symfonyMailer = new SymfonyMailer($transport);
        return $this;
    }

    public function getConfig(): MailConfig
    {
        return $this->config;
    }

    protected function createDefaultTransport(MailConfig $config): TransportInterface
    {
        if ($config->username && $config->password) {
            $dsn = sprintf(
                'smtp://%s:%s@%s:%d',
                rawurlencode($config->username),
                rawurlencode($config->password),
                $config->host,
                $config->port
            );
        } else {
            $dsn = "smtp://{$config->host}:{$config->port}";
        }

        return Transport::fromDsn($dsn);
    }
}
