<?php

namespace YasserElgammal\Green\Providers;

use YasserElgammal\Green\Support\ServiceProvider;
use YasserElgammal\Green\Database\ConnectionPool;
use YasserElgammal\Green\Database\Database;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectionPool::class, function ($app) {
            $config = $app->make('config')->get('database', [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'dbname'   => $_ENV['DB_NAME']     ?? 'green_framework',
                        'user'     => $_ENV['DB_USER']     ?? 'root',
                        'password' => $_ENV['DB_PASSWORD'] ?? '',
                        'host'     => $_ENV['DB_HOST']     ?? '127.0.0.1',
                        'port'     => (int) ($_ENV['DB_PORT'] ?? 3306),
                        'driver'   => $_ENV['DB_DRIVER']   ?? 'pdo_mysql',
                    ],
                ],
            ]);

            return new ConnectionPool($config);
        });
    }

    public function boot(): void
    {
        $pool = $this->app->make(ConnectionPool::class);
        Database::setPool($pool);
    }
}
