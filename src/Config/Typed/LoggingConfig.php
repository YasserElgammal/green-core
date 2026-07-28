<?php

namespace YasserElgammal\Green\Config\Typed;

use YasserElgammal\Green\Config\Contracts\ConfigReaderInterface;

final readonly class LoggingConfig
{
    public function __construct(public string $path)
    {
    }

    public static function fromRepository(ConfigReaderInterface $config): self
    {
        return new self((string) $config->get('logging.path'));
    }
}
