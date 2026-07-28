<?php

namespace YasserElgammal\Green\Config\Typed;

use YasserElgammal\Green\Config\Contracts\ConfigReaderInterface;

final readonly class DatabaseConfig
{
    public function __construct(public string $default, public array $connections)
    {
    }

    public static function fromRepository(ConfigReaderInterface $config): self
    {
        return new self(
            (string) $config->get('database.default', 'mysql'),
            (array) $config->get('database.connections', []),
        );
    }

    public function toArray(): array
    {
        return ['default' => $this->default, 'connections' => $this->connections];
    }
}
