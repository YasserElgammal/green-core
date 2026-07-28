<?php

namespace YasserElgammal\Green\Config\Contracts;

interface ConfigRedactorInterface
{
    public function redact(array $configuration): array;
}
