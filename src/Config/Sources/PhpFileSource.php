<?php

namespace YasserElgammal\Green\Config\Sources;

use YasserElgammal\Green\Config\Contracts\ConfigSourceInterface;
use YasserElgammal\Green\Config\Exceptions\ConfigurationException;

final readonly class PhpFileSource implements ConfigSourceInterface
{
    public function __construct(private string $path)
    {
    }

    public function load(): array
    {
        if (!is_file($this->path)) return [];
        $items = require $this->path;
        if (!is_array($items)) {
            throw new ConfigurationException("Configuration cache [{$this->path}] must return an array.");
        }
        if (isset($items['_meta'], $items['config']) && is_array($items['config'])) {
            return $items['config'];
        }

        return $items;
    }
}
