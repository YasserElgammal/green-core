<?php

namespace YasserElgammal\Green\Config\Typed;

use YasserElgammal\Green\Config\Contracts\ConfigReaderInterface;

final readonly class ViewConfig
{
    public function __construct(
        public string $path,
        public bool $cache,
        public string $cachePath,
        public bool $debug,
    ) {
    }

    public static function fromRepository(ConfigReaderInterface $config): self
    {
        return new self(
            (string) $config->get('view.path'),
            (bool) $config->get('view.cache', false),
            (string) $config->get('view.cache_path'),
            (bool) $config->get('app.debug', false),
        );
    }
}
