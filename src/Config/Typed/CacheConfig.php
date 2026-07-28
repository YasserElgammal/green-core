<?php

namespace YasserElgammal\Green\Config\Typed;

use YasserElgammal\Green\Config\Contracts\ConfigReaderInterface;

final readonly class CacheConfig
{
    public function __construct(public string $default, public array $stores)
    {
    }

    public static function fromRepository(ConfigReaderInterface $config): self
    {
        return new self(
            (string) $config->get('cache.default', 'file'),
            (array) $config->get('cache.stores', []),
        );
    }

    public function toArray(): array
    {
        return ['default' => $this->default, 'stores' => $this->stores];
    }
}
