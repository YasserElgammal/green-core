<?php

namespace YasserElgammal\Green\Notifications\Channel;

use YasserElgammal\Green\Notifications\NotificationChannelInterface;

class SMSChannel implements NotificationChannelInterface
{
    public function send(object $notifiable, \YasserElgammal\Green\Notifications\Notification $notification): void
    {
        throw new \LogicException('Configure a custom SMS channel with a real transport before sending notifications.');
    }
}
