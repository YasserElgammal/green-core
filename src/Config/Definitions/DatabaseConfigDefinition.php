<?php

namespace YasserElgammal\Green\Config\Definitions;

use YasserElgammal\Green\Config\Contracts\ConfigDefinitionInterface;

final class DatabaseConfigDefinition implements ConfigDefinitionInterface
{
    public function defaults(string $basePath): array
    {
        return ['database' => [
            'default' => 'mysql',
            'connections' => ['mysql' => [
                'dbname' => 'green_framework', 'user' => 'root', 'password' => '',
                'host' => '127.0.0.1', 'port' => 3306, 'driver' => 'pdo_mysql',
            ]],
        ]];
    }

    public function environmentMap(): array
    {
        return [
            'database.connections.mysql.dbname' => ['env' => 'DB_NAME'],
            'database.connections.mysql.user' => ['env' => 'DB_USER'],
            'database.connections.mysql.password' => ['env' => 'DB_PASSWORD'],
            'database.connections.mysql.host' => ['env' => 'DB_HOST'],
            'database.connections.mysql.port' => ['env' => 'DB_PORT', 'type' => 'int'],
            'database.connections.mysql.driver' => ['env' => 'DB_DRIVER'],
        ];
    }

}
