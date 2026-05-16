<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Exceptions;

/**
 * Thrown when the include query string contains malformed syntax.
 *
 * Examples: unmatched parentheses, unexpected characters, missing values.
 * Includes the character position for debuggability.
 */
class SyntaxException extends IncludeQueryException
{
    public function __construct(
        string $message,
        private readonly int $position = 0,
        private readonly string $source = '',
    ) {
        $positionHint = $position > 0 ? " at position {$position}" : '';
        $sourceHint   = $source !== '' ? " in \"{$source}\"" : '';

        parent::__construct("Include query syntax error{$positionHint}{$sourceHint}: {$message}");
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getSource(): string
    {
        return $this->source;
    }
}
