<?php

namespace YasserElgammal\Green\Notifications;

abstract class Notification
{
    /**
     * Get the notification's delivery channels.
     *
     * @param object $notifiable
     * @return array<int, string>
     */
    abstract public function via(object $notifiable): array;
}
