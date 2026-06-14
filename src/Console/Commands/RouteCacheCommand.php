<?php

namespace YasserElgammal\Green\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use YasserElgammal\Green\Routing\RouteCache;
use YasserElgammal\Green\Routing\Router;

#[AsCommand(
    name: 'route:cache',
    description: 'Compile controller routes into a cache file',
)]
class RouteCacheCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument('controllers', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Controller classes to cache')
            ->addOption('controllers-file', null, InputOption::VALUE_OPTIONAL, 'PHP file returning an array of controller classes')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Controller directory to auto-discover', 'app/Controllers')
            ->addOption('file', null, InputOption::VALUE_OPTIONAL, 'Route cache file path');
    }

    protected function handle(): int
    {
        $controllers = $this->resolveControllers();

        if ($controllers === []) {
            $this->error('No controllers were found. Pass controller classes, use --controllers-file, or set --path.');
            return Command::FAILURE;
        }

        $router = new Router();

        foreach ($controllers as $controller) {
            if (!class_exists($controller)) {
                $this->error("Controller class does not exist: {$controller}");
                return Command::FAILURE;
            }

            $router->registerRoutesFromController($controller);
        }

        $cacheFile = $this->resolveCacheFile();
        RouteCache::clear($cacheFile);
        $router->cacheRoutes($cacheFile);

        $this->success('Route cache generated successfully.');
        $this->line("Cached " . count($controllers) . " controller(s) to {$cacheFile}");

        return Command::SUCCESS;
    }

    /**
     * @return list<class-string>
     */
    private function resolveControllers(): array
    {
        $controllers = [];
        $file = $this->option('controllers-file');

        if (is_string($file) && $file !== '') {
            $path = $this->absolutePath($file);

            if (!file_exists($path)) {
                throw new \RuntimeException("Controllers file does not exist: {$path}");
            }

            $fromFile = require $path;

            if (!is_array($fromFile)) {
                throw new \RuntimeException("Controllers file must return an array: {$path}");
            }

            $controllers = array_merge($controllers, $fromFile);
        }

        $argumentControllers = $this->argument('controllers');

        if (is_array($argumentControllers)) {
            $controllers = array_merge($controllers, $argumentControllers);
        }

        if ($controllers === []) {
            $controllers = $this->discoverControllers();
        }

        return array_values(array_unique(array_filter($controllers, 'is_string')));
    }

    /**
     * @return list<class-string>
     */
    private function discoverControllers(): array
    {
        $pathOption = $this->option('path');
        $directory = $this->absolutePath(is_string($pathOption) && $pathOption !== '' ? $pathOption : 'app/Controllers');

        if (!is_dir($directory)) {
            return [];
        }

        $controllers = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $class = $this->classFromFile($file->getPathname());

            if ($class !== null && str_ends_with($class, 'Controller')) {
                $controllers[] = $class;
            }
        }

        return $controllers;
    }

    /**
     * @return class-string|null
     */
    private function classFromFile(string $file): ?string
    {
        $source = file_get_contents($file);

        if ($source === false) {
            return null;
        }

        $tokens = token_get_all($source);
        $namespace = '';
        $class = null;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $namespace = $this->readNamespace($tokens, $i + 1);
                continue;
            }

            if (is_array($token) && $token[0] === T_CLASS) {
                $class = $this->readClassName($tokens, $i + 1);
                break;
            }
        }

        if ($class === null) {
            return null;
        }

        return $namespace !== '' ? $namespace . '\\' . $class : $class;
    }

    /**
     * @param array<int, mixed> $tokens
     */
    private function readNamespace(array $tokens, int $offset): string
    {
        $namespace = '';
        $count = count($tokens);

        for ($i = $offset; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token === ';' || $token === '{') {
                break;
            }

            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                $namespace .= $token[1];
            }
        }

        return $namespace;
    }

    /**
     * @param array<int, mixed> $tokens
     */
    private function readClassName(array $tokens, int $offset): ?string
    {
        $count = count($tokens);

        for ($i = $offset; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }
        }

        return null;
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
