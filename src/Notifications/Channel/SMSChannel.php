<?php

namespace YasserElgammal\Green\Notifications\Channel;

use YasserElgammal\Green\Notifications\NotificationChannelInterface;

class SMSChannel implements NotificationChannelInterface
{
    public function send(string $recipient, string $message): bool
    {
        error_log("Sending SMS to {$recipient}: {$message}");
        return true;
    }
}
