<?php

namespace YasserElgammal\Green\Config\Security;

use YasserElgammal\Green\Config\Contracts\ConfigRedactorInterface;

final readonly class SecretRedactor implements ConfigRedactorInterface
{
    /** @param list<string> $patterns */
    public function __construct(
        private array $patterns = ['password', 'secret', 'token', 'private[_-]?key', 'api[_-]?key'],
        private string $replacement = '********',
    ) {
    }

    public function redact(array $configuration): array
    {
        foreach ($configuration as $key => $value) {
            if ($this->isSensitive((string) $key)) {
                $configuration[$key] = $value === null ? null : $this->replacement;
            } elseif (is_array($value)) {
                $configuration[$key] = $this->redact($value);
            }
        }

        return $configuration;
    }

    private function isSensitive(string $key): bool
    {
        foreach ($this->patterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $key) === 1) {
                return true;
            }
        }

        return false;
    }
}
