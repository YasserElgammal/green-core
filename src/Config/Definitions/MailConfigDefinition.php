<?php

namespace YasserElgammal\Green\Config\Definitions;

use YasserElgammal\Green\Config\Contracts\ConfigDefinitionInterface;

final class MailConfigDefinition implements ConfigDefinitionInterface
{
    public function defaults(string $basePath): array
    {
        return ['mail' => [
            'host' => '127.0.0.1', 'port' => 1025, 'username' => null, 'password' => null,
            'from' => ['address' => 'hello@example.com', 'name' => 'Example'],
        ]];
    }

    public function environmentMap(): array
    {
        return [
            'mail.host' => ['env' => 'MAIL_HOST'],
            'mail.port' => ['env' => 'MAIL_PORT', 'type' => 'int'],
            'mail.username' => ['env' => 'MAIL_USERNAME'],
            'mail.password' => ['env' => 'MAIL_PASSWORD'],
            'mail.from.address' => ['env' => 'MAIL_FROM_ADDRESS'],
            'mail.from.name' => ['env' => 'MAIL_FROM_NAME'],
        ];
    }

}
