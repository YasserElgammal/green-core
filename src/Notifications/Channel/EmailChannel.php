<?php

namespace YasserElgammal\Green\Notifications\Channel;

use YasserElgammal\Green\Notifications\NotificationChannelInterface;

class EmailChannel implements NotificationChannelInterface
{
    public function send(string $recipient, string $message): bool
    {
        error_log("Sending Email to {$recipient}: {$message}");
        return true;
    }
}
