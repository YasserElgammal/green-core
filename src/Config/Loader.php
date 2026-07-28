<?php

namespace YasserElgammal\Green\Config;

use YasserElgammal\Green\Config\Contracts\ConfigSourceInterface;

final class Loader
{
    /** @var list<ConfigSourceInterface> */
    private array $sources = [];

    public function addSource(ConfigSourceInterface $source): self
    {
        $this->sources[] = $source;
        return $this;
    }

    public function load(): array
    {
        $items = [];
        foreach ($this->sources as $source) {
            $items = Merger::merge($items, $source->load());
        }
        return $items;
    }
}
