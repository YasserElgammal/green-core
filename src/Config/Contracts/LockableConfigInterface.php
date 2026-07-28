<?php

namespace YasserElgammal\Green\Config\Contracts;

interface LockableConfigInterface
{
    public function lock(): void;
    public function isLocked(): bool;
}
