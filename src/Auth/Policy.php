<?php

namespace YasserElgammal\Green\Auth;

abstract class Policy
{
    /**
     * Determine if the given ability should be granted for the current user.
     * Overriding this method allows you to authorize all actions for a specific user (e.g. admin).
     *
     * @param mixed $actor
     * @param string $ability
     * @return bool|null
     */
    public function before(mixed $actor, string $ability): ?bool
    {
        return null; // Fall through to the specific ability method
    }
}
