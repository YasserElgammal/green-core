<?php

namespace YasserElgammal\Green\Notifications\Channel;

use YasserElgammal\Green\Notifications\NotificationChannelInterface;
use YasserElgammal\Green\Notifications\Notification;
use YasserElgammal\Green\View\View;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class MailChannel implements NotificationChannelInterface
{
    protected Mailer $mailer;

    public function __construct()
    {
        // Simple default parsing, fallback to env vars natively
        $host = $_ENV['MAIL_HOST'] ?? '127.0.0.1';
        $port = $_ENV['MAIL_PORT'] ?? 1025;
        $username = $_ENV['MAIL_USERNAME'] ?? null;
        $password = $_ENV['MAIL_PASSWORD'] ?? null;
        
        // e.g. smtp://user:pass@smtp.example.com:25 or smtp://127.0.0.1:1025
        if ($username && $password) {
            $dsn = "smtp://{$username}:{$password}@{$host}:{$port}";
        } else {
            $dsn = "smtp://{$host}:{$port}";
        }

        $transport = Transport::fromDsn($dsn);
        $this->mailer = new Mailer($transport);
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

        $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'hello@example.com';
        $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Example';

        $email = (new Email())
            ->from("{$fromName} <{$fromAddress}>")
            ->to($recipient)
            ->subject($message->subject)
            ->html($html);

        $this->mailer->send($email);
    }
}
