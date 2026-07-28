<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use YasserElgammal\Green\Config\ConfigCache;
use YasserElgammal\Green\Config\ConfigManager;
use YasserElgammal\Green\Config\Contracts\ConfigReaderInterface;

#[AsCommand(name: 'config:cache', description: 'Cache the merged configuration')]
final class ConfigCacheCommand extends BaseCommand
{
    public function __construct(
        private ConfigReaderInterface $config,
        private ConfigCache $cache,
        private ConfigManager $manager,
    )
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        $this->cache->write($this->config->all(), $this->manager->fingerprint());
        $this->success('Configuration cached successfully.');
        return Command::SUCCESS;
    }
}
