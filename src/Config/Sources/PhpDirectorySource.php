<?php

namespace YasserElgammal\Green\Config\Sources;

use YasserElgammal\Green\Config\Contracts\ConfigSourceInterface;
use YasserElgammal\Green\Config\Exceptions\ConfigurationException;

final readonly class PhpDirectorySource implements ConfigSourceInterface
{
    public function __construct(private string $path)
    {
    }

    public function load(): array
    {
        if (!is_dir($this->path)) {
            return [];
        }

        $configuration = [];
        $files = glob($this->path . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            $values = require $file;
            if (!is_array($values)) {
                throw new ConfigurationException("Configuration file [{$file}] must return an array.");
            }
            $configuration[basename($file, '.php')] = $values;
        }

        return $configuration;
    }
}
