<?php

namespace YasserElgammal\Green\Config;

use YasserElgammal\Green\Config\Contracts\ConfigDefinitionInterface;

final class DefinitionRegistry
{
    /** @var list<ConfigDefinitionInterface> */
    private array $definitions = [];

    public function register(ConfigDefinitionInterface $definition): self
    {
        $this->definitions[] = $definition;
        return $this;
    }

    public function defaults(string $basePath): array
    {
        $result = [];
        foreach ($this->definitions as $definition) {
            $result = Merger::merge($result, $definition->defaults($basePath));
        }
        return $result;
    }

    public function environmentMap(): array
    {
        $result = [];
        foreach ($this->definitions as $definition) {
            $result = array_replace($result, $definition->environmentMap());
        }
        return $result;
    }

    public function fingerprint(string $basePath): string
    {
        return hash('sha256', serialize([
            $this->defaults($basePath),
            $this->environmentMap(),
            array_map(static fn (object $definition) => $definition::class, $this->definitions),
        ]));
    }
}
