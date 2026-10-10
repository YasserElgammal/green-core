<?php
declare(strict_types=1);

namespace YasserElgammal\Green\Config\Typed;

use YasserElgammal\Green\Config\Contracts\ConfigReaderInterface;

final readonly class QueueConfig
{
    public function __construct(
        public string $default,
        public array $connections,
    ) {}

    public static function fromRepository(ConfigReaderInterface $config): self
    {
        return new self(
            (string) $config->get('queue.default', 'sync'),
            (array) $config->get('queue.connections', []),
        );
    }

    public function toArray(): array
    {
        return ['default' => $this->default, 'connections' => $this->connections];
    }
}
