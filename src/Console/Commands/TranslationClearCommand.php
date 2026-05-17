<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use YasserElgammal\Green\Translation\TranslatorManager;

#[AsCommand(
    name: 'translation:clear',
    description: 'Clear all cached translations',
)]
class TranslationClearCommand extends BaseCommand
{
    protected function handle(): int
    {
        $this->info('Clearing translation cache...');

        try {
            TranslatorManager::getInstance()->flushCache();
            $this->success('Translation cache cleared successfully.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to clear translation cache: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
