<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use YasserElgammal\Green\Config\Contracts\ConfigReaderInterface;
use YasserElgammal\Green\Config\Contracts\ConfigRedactorInterface;

#[AsCommand(name: 'config:show', description: 'Show configuration with secrets redacted')]
final class ConfigShowCommand extends BaseCommand
{
    public function __construct(
        private ConfigReaderInterface $config,
        private ConfigRedactorInterface $redactor,
    )
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        $safe = $this->redactor->redact($this->config->all());
        $this->line((string) json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return Command::SUCCESS;
    }
}
