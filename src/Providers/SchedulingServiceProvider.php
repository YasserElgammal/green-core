<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Providers;

use Throwable;
use YasserElgammal\Green\Cache\CacheManager;
use YasserElgammal\Green\Queue\QueueManager;
use YasserElgammal\Green\Scheduling\Contracts\MutexInterface;
use YasserElgammal\Green\Scheduling\Mutex\CacheMutex;
use YasserElgammal\Green\Scheduling\Mutex\InMemoryMutex;
use YasserElgammal\Green\Scheduling\Schedule;
use YasserElgammal\Green\Scheduling\ScheduleRunner;
use YasserElgammal\Green\Signal\SignalDispatcher;
use YasserElgammal\Green\Support\ServiceProvider;

class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MutexInterface::class, function ($app) {
            try {
                $cache = $app->make(CacheManager::class)->store();
                return new CacheMutex($cache);
            } catch (Throwable) {
                return new InMemoryMutex();
            }
        });

        $this->app->singleton(Schedule::class, fn () => new Schedule());

        $this->app->singleton(ScheduleRunner::class, function ($app) {
            return new ScheduleRunner(
                mutex: $app->make(MutexInterface::class),
                signals: $app->make(SignalDispatcher::class),
                queue: $app->make(QueueManager::class),
                jobResolver: fn (string $class): object => $app->make($class),
            );
        });
    }
}
