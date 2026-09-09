<?php

namespace YasserElgammal\Green\Tests\Notifications;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use YasserElgammal\Green\Notifications\Channel\DatabaseChannel;
use YasserElgammal\Green\Notifications\Channel\MailChannel;
use YasserElgammal\Green\Notifications\Messages\MailMessage;
use YasserElgammal\Green\Notifications\Notification;

final class NotificationChannelsTest extends TestCase
{
    public function test_mail_message_builds_subject_template_and_data_fluently(): void
    {
        $message = (new MailMessage())
            ->subject('Account created')
            ->template('mail.welcome', ['name' => 'Green']);

        self::assertSame('Account created', $message->subject);
        self::assertSame('mail.welcome', $message->template);
        self::assertSame(['name' => 'Green'], $message->data);
    }

    public function test_database_channel_requires_database_representation(): void
    {
        $notification = new class extends Notification {
            public function via(object $notifiable): array { return ['database']; }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing toDatabase method');

        (new DatabaseChannel())->send(new \stdClass(), $notification);
    }

    public function test_database_channel_requires_a_notifiable_identifier(): void
    {
        $notification = new class extends Notification {
            public function via(object $notifiable): array { return ['database']; }
            public function toDatabase(object $notifiable): array { return ['event' => 'created']; }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing getId()');

        (new DatabaseChannel())->send(new \stdClass(), $notification);
    }

    public function test_mail_channel_rejects_notification_without_mail_representation(): void
    {
        $notification = new class extends Notification {
            public function via(object $notifiable): array { return ['mail']; }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing toMail method');

        (new MailChannel())->send(new \stdClass(), $notification);
    }
}
