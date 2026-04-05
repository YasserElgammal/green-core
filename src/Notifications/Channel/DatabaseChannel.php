<?php

namespace YasserElgammal\Green\Notifications\Channel;

use YasserElgammal\Green\Notifications\NotificationChannelInterface;
use YasserElgammal\Green\Notifications\Notification;
use RuntimeException;

class DatabaseChannel implements NotificationChannelInterface
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toDatabase')) {
            throw new RuntimeException('Notification is missing toDatabase method.');
        }

        $data = $notification->toDatabase($notifiable);
        $id = method_exists($notifiable, 'getId') ? $notifiable->getId() : null;

        if (!$id) {
            throw new RuntimeException('Notifiable object is missing getId() for DatabaseChannel.');
        }

        // Ideally, we'd insert into a 'notifications' table:
        // Model::table('notifications')->insert([
        //     'id' => uniqid(),
        //     'type' => get_class($notification),
        //     'notifiable_type' => get_class($notifiable),
        //     'notifiable_id' => $id,
        //     'data' => json_encode($data),
        //     'read_at' => null,
        //     'created_at' => date('Y-m-d H:i:s')
        // ]);
        
        error_log("DatabaseChannel: Inserted notification for Notifiable ({$id}): " . json_encode($data));
    }
}
