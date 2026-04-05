<?php

namespace YasserElgammal\Green\Notifications;

interface NotificationChannelInterface
{
    /**
     * Send the given notification to the given notifiable entity.
     *
     * @param object $notifiable
     * @param Notification $notification
     * @return void
     */
    public function send(object $notifiable, Notification $notification): void;
}
