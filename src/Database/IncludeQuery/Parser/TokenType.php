<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Parser;

/**
 * Token types produced by the Tokenizer.
 */
enum TokenType: string
{
    case Identifier = 'IDENTIFIER';
    case ParenOpen  = 'PAREN_OPEN';
    case ParenClose = 'PAREN_CLOSE';
    case Colon      = 'COLON';
    case Comma      = 'COMMA';
    case Dot        = 'DOT';
    case Equals     = 'EQUALS';
    case Eof        = 'EOF';
}
