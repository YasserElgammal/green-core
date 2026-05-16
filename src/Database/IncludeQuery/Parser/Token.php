<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Parser;

/**
 * A single token produced by the Tokenizer.
 *
 * Immutable value object carrying the type, lexeme, and source position.
 */
final readonly class Token
{
    public function __construct(
        public TokenType $type,
        public string    $value,
        public int       $position,
    ) {
    }

    public function is(TokenType $type): bool
    {
        return $this->type === $type;
    }
}
