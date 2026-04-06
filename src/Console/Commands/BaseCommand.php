<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class BaseCommand extends Command
{
    protected InputInterface $input;
    protected OutputInterface $output;
    protected SymfonyStyle $io;

    /**
     * Called by Symfony Console — sets up io then delegates to run()
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input  = $input;
        $this->output = $output;
        $this->io     = new SymfonyStyle($input, $output);

        return $this->handle();
    }

    /**
     * Every command implements this instead of execute()
     */
    abstract protected function handle(): int;

    // ── Output helpers ────────────────────────────────────────────────────────

    protected function info(string $message): void
    {
        $this->io->writeln("<info>$message</info>");
    }

    protected function error(string $message): void
    {
        $this->io->writeln("<error>$message</error>");
    }

    protected function warn(string $message): void
    {
        $this->io->writeln("<comment>$message</comment>");
    }

    protected function success(string $message): void
    {
        $this->io->success($message);
    }

    protected function line(string $message): void
    {
        $this->io->writeln($message);
    }

    // ── Input helpers ─────────────────────────────────────────────────────────

    protected function argument(string $name): mixed
    {
        return $this->input->getArgument($name);
    }

    protected function option(string $name): mixed
    {
        return $this->input->getOption($name);
    }

    protected function ask(string $question, string $default = ''): mixed
    {
        return $this->io->ask($question, $default);
    }

    protected function confirm(string $question, bool $default = false): bool
    {
        return $this->io->confirm($question, $default);
    }

    // ── Filesystem helpers ────────────────────────────────────────────────────

    /**
     * Returns the project root (where vendor/ lives)
     */
    protected function basePath(string $path = ''): string
    {
        $root = getcwd(); // Use current working directory as project root
        return $root . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/') : '');
    }

    protected function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, recursive: true);
        }
    }

    protected function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    protected function putFile(string $path, string $content): void
    {
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);
    }

    /**
     * Load a stub file and replace placeholders
     *
     * @param  array<string, string>  $replacements
     */
    protected function renderStub(string $stubName, array $replacements = []): string
    {
        $stubPath = dirname(__DIR__) . "/Stubs/{$stubName}.stub";

        if (!file_exists($stubPath)) {
            throw new \RuntimeException("Stub [{$stubName}] not found at {$stubPath}");
        }

        $content = file_get_contents($stubPath);

        foreach ($replacements as $placeholder => $value) {
            $content = str_replace("{{ {$placeholder} }}", $value, $content);
        }

        return $content;
    }
}
