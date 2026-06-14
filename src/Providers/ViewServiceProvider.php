<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\View\View;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $config = $this->app->make('config');
        $basePath = defined('BASE_PATH') ? rtrim(constant('BASE_PATH'), '/\\') : (getcwd() ?: '.');

        $viewsPath = $config->get('view.path', $basePath . '/views');
        $cachePath = $config->get('view.cache_path', $basePath . '/storage/cache/views');
        $debug = $config->get('app.debug', false);

        if (!$config->get('view.cache', false)) {
            $cachePath = null;
        }

        View::init($viewsPath, $cachePath, $debug);
    }
}
