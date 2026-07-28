<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\View\View;
use YasserElgammal\Green\View\ViewRenderer;
use YasserElgammal\Green\Config\Typed\ViewConfig;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ViewRenderer::class, function ($app) {
            $settings = $app->make(ViewConfig::class);

            return new ViewRenderer(
                $settings->path,
                $settings->cache ? $settings->cachePath : null,
                $settings->debug,
            );
        });
    }

    public function boot(): void
    {
        View::setResolver(fn () => $this->app->make(ViewRenderer::class));
    }
}
