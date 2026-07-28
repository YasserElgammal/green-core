<?php

namespace YasserElgammal\Green\Config\Contracts;

interface ConfigSourceInterface
{
    /** @return array<string, mixed> */
    public function load(): array;
}
