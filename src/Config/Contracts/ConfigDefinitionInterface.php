<?php

namespace YasserElgammal\Green\Config\Contracts;

interface ConfigDefinitionInterface
{
    /** @return array<string, mixed> */
    public function defaults(string $basePath): array;

    /** @return array<string, array{env:string,type?:string}> */
    public function environmentMap(): array;
}
