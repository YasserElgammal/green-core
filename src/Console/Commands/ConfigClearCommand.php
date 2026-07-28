<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use YasserElgammal\Green\Config\ConfigCache;

#[AsCommand(name: 'config:clear', description: 'Remove the configuration cache')]
final class ConfigClearCommand extends BaseCommand
{
    public function __construct(private ConfigCache $cache)
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        if (!$this->cache->clear()) {
            $this->error('Unable to clear configuration cache.');
            return Command::FAILURE;
        }
        $this->success('Configuration cache cleared.');
        return Command::SUCCESS;
    }
}
