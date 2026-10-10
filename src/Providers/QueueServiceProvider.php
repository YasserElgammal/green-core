<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\Queue\QueueManager;
use YasserElgammal\Green\Queue\Worker;
use YasserElgammal\Green\Config\Typed\QueueConfig;

class QueueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QueueManager::class, function ($app) {
            $settings = $app->make(QueueConfig::class);
            return new QueueManager($settings->toArray());
        });

        $this->app->singleton(Worker::class, function ($app) {
            return new Worker($app->make(QueueManager::class));
        });
    }
}
