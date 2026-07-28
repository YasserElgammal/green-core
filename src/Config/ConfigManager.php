<?php

namespace YasserElgammal\Green\Config;

use YasserElgammal\Green\Config\Exceptions\ConfigurationException;
use YasserElgammal\Green\Config\Sources\ArraySource;
use YasserElgammal\Green\Config\Sources\EnvironmentSource;
use YasserElgammal\Green\Config\Sources\PhpDirectorySource;
use YasserElgammal\Green\Config\Sources\PhpFileSource;

final class ConfigManager
{
    private ConfigState $state = ConfigState::Collecting;
    private ?Repository $repository = null;

    public function __construct(
        private readonly string $basePath,
        private readonly string $configPath,
        private readonly DefinitionRegistry $definitions,
        private readonly ConfigCache $cache,
    ) {
    }

    public function load(array $overrides = []): Repository
    {
        $this->expect(ConfigState::Collecting);
        $fingerprint = $this->definitions->fingerprint($this->basePath);
        $loader = new Loader();

        if ($this->cache->isCompatible($fingerprint)) {
            $loader->addSource(new PhpFileSource($this->cache->path()));
        } else {
            $loader->addSource(new ArraySource($this->definitions->defaults($this->basePath)))
                ->addSource(new EnvironmentSource($this->definitions->environmentMap()))
                ->addSource(new PhpDirectorySource($this->configPath));
        }

        $loader->addSource(new ArraySource($overrides));
        $this->repository = new Repository($loader->load());
        $this->state = ConfigState::Loaded;

        return $this->repository;
    }

    public function lock(): void
    {
        $this->expect(ConfigState::Loaded);
        $this->repository()->lock();
        $this->state = ConfigState::Locked;
    }

    public function state(): ConfigState
    {
        return $this->state;
    }

    public function repository(): Repository
    {
        return $this->repository
            ?? throw new ConfigurationException('Configuration has not been loaded.');
    }

    public function fingerprint(): string
    {
        return $this->definitions->fingerprint($this->basePath);
    }

    private function expect(ConfigState $expected): void
    {
        if ($this->state !== $expected) {
            throw new ConfigurationException(
                "Invalid configuration lifecycle transition from [{$this->state->value}]; expected [{$expected->value}].",
            );
        }
    }
}
