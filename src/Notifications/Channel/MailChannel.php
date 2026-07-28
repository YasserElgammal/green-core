<?php

namespace YasserElgammal\Green\Notifications\Channel;

use YasserElgammal\Green\Notifications\NotificationChannelInterface;
use YasserElgammal\Green\Notifications\Notification;
use YasserElgammal\Green\View\View;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use YasserElgammal\Green\Config\Typed\MailConfig;

class MailChannel implements NotificationChannelInterface
{
    protected Mailer $mailer;
    private MailConfig $config;

    public function __construct(?MailConfig $config = null)
    {
        $mail = $config
            ?? new MailConfig('127.0.0.1', 1025, null, null, 'hello@example.com', 'Example');
        
        // e.g. smtp://user:pass@smtp.example.com:25 or smtp://127.0.0.1:1025
        if ($mail->username && $mail->password) {
            $dsn = sprintf('smtp://%s:%s@%s:%d', rawurlencode($mail->username), rawurlencode($mail->password), $mail->host, $mail->port);
        } else {
            $dsn = "smtp://{$mail->host}:{$mail->port}";
        }

        $transport = Transport::fromDsn($dsn);
        $this->mailer = new Mailer($transport);
        $this->config = $mail;
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toMail')) {
            throw new \RuntimeException('Notification is missing toMail method.');
        }

        $message = $notification->toMail($notifiable);
        $recipient = $notifiable->routeNotificationFor('mail');

        if (!$recipient) {
            return;
        }

        $html = View::render($message->template, $message->data);

        $fromAddress = $this->config->fromAddress;
        $fromName = $this->config->fromName;

        $email = (new Email())
            ->from("{$fromName} <{$fromAddress}>")
            ->to($recipient)
            ->subject($message->subject)
            ->html($html);

        $this->mailer->send($email);
    }
}
