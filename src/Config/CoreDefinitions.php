<?php

namespace YasserElgammal\Green\Config;

use YasserElgammal\Green\Config\Definitions\ApplicationConfigDefinition;
use YasserElgammal\Green\Config\Definitions\DatabaseConfigDefinition;
use YasserElgammal\Green\Config\Definitions\InfrastructureConfigDefinition;
use YasserElgammal\Green\Config\Definitions\MailConfigDefinition;

final class CoreDefinitions
{
    public static function registry(iterable $additional = []): DefinitionRegistry
    {
        $registry = (new DefinitionRegistry())
            ->register(new ApplicationConfigDefinition())
            ->register(new DatabaseConfigDefinition())
            ->register(new MailConfigDefinition())
            ->register(new InfrastructureConfigDefinition());

        foreach ($additional as $definition) {
            $registry->register($definition);
        }

        return $registry;
    }
}
