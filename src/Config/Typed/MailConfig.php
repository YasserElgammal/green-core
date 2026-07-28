<?php

namespace YasserElgammal\Green\Config\Typed;

use YasserElgammal\Green\Config\Contracts\ConfigReaderInterface;

final readonly class MailConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public ?string $username,
        #[\SensitiveParameter] public ?string $password,
        public string $fromAddress,
        public string $fromName,
    ) {
    }

    public static function fromRepository(ConfigReaderInterface $config): self
    {
        return new self(
            (string) $config->get('mail.host', '127.0.0.1'),
            (int) $config->get('mail.port', 1025),
            $config->get('mail.username'), $config->get('mail.password'),
            (string) $config->get('mail.from.address', 'hello@example.com'),
            (string) $config->get('mail.from.name', 'Example'),
        );
    }
}
