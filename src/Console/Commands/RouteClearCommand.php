<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use YasserElgammal\Green\Routing\RouteCache;

#[AsCommand(
    name: 'route:clear',
    description: 'Clear the compiled route cache',
)]
class RouteClearCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addOption('file', null, InputOption::VALUE_OPTIONAL, 'Route cache file path');
    }

    protected function handle(): int
    {
        $cacheFile = $this->resolveCacheFile();

        if (!file_exists($cacheFile)) {
            $this->warn("Route cache file does not exist: {$cacheFile}");
            return Command::SUCCESS;
        }

        if (!RouteCache::clear($cacheFile)) {
            $this->error("Failed to clear route cache: {$cacheFile}");
            return Command::FAILURE;
        }

        $this->success('Route cache cleared successfully.');
        return Command::SUCCESS;
    }

    private function resolveCacheFile(): string
    {
        $file = $this->option('file');

        if (is_string($file) && $file !== '') {
            return $this->absolutePath($file);
        }

        return RouteCache::defaultPath();
    }

    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            return $path;
        }

        return $this->basePath($path);
    }
}
