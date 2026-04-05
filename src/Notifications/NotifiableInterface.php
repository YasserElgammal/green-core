<?php

namespace YasserElgammal\Green\Notifications;

interface NotifiableInterface
{
    /**
     * Get the notification routing information for the given channel.
     *
     * @param string $channel
     * @return mixed
     */
    public function routeNotificationFor(string $channel): mixed;
}
