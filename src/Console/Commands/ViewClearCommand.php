<?php

namespace YasserElgammal\Green\Console\Commands;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use YasserElgammal\Green\Config\Typed\ViewConfig;

#[AsCommand(name: 'view:clear', description: 'Remove compiled view templates')]
final class ViewClearCommand extends BaseCommand
{
    public function __construct(private readonly ViewConfig $config)
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        $path = rtrim($this->config->cachePath, '/\\');

        if ($path === '' || dirname($path) === $path) {
            $this->error('The configured view cache path is not safe to clear.');
            return Command::FAILURE;
        }

        if (!is_dir($path)) {
            $this->success('View cache cleared.');
            return Command::SUCCESS;
        }

        try {
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($items as $item) {
                $removed = $item->isDir() && !$item->isLink()
                    ? rmdir($item->getPathname())
                    : unlink($item->getPathname());

                if (!$removed) {
                    throw new \RuntimeException('Unable to remove ' . $item->getPathname());
                }
            }

            $this->success('View cache cleared.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Unable to clear view cache: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
