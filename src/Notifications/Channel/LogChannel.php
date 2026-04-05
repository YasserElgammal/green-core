<?php

namespace YasserElgammal\Green\Notifications\Channel;

use YasserElgammal\Green\Notifications\NotificationChannelInterface;
use YasserElgammal\Green\Notifications\Notification;

class LogChannel implements NotificationChannelInterface
{
    public function send(object $notifiable, Notification $notification): void
    {
        $id = method_exists($notifiable, 'getId') ? $notifiable->getId() : 'Unknown';

        // Check for specific methods or just var_export the whole notification
        $data = [];
        if (method_exists($notification, 'toLog')) {
            $data = $notification->toLog($notifiable);
        } elseif (method_exists($notification, 'toMail')) {
            $mail = $notification->toMail($notifiable);
            $data = ['subject' => $mail->subject, 'template' => $mail->template, 'data' => $mail->data];
        }

        $logData = json_encode([
            'notifiable' => $id,
            'notification' => get_class($notification),
            'data' => $data
        ]);

        // Writing to system log
        error_log("[LogChannel] Sending Notification: " . $logData);
    }
}
