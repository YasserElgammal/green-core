<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Parser;

use YasserElgammal\Green\Database\IncludeQuery\Exceptions\SyntaxException;

/**
 * Character-by-character lexer for the Include Query Language.
 *
 * Converts a raw string into a stream of typed tokens.
 * No regex spaghetti — pure procedural scanning.
 *
 * Input:  'comments(limit:5,order:desc).author(select:id|name)'
 * Output: Token[] stream terminated by EOF
 */
final class Tokenizer
{
    private string $source;
    private int    $length;
    private int    $cursor = 0;

    /**
     * Tokenize the given include query string.
     *
     * @param  string  $source
     * @return Token[]
     *
     * @throws SyntaxException on unexpected characters
     */
    public function tokenize(string $source): array
    {
        $this->source = $source;
        $this->length = strlen($source);
        $this->cursor = 0;

        $tokens = [];

        while ($this->cursor < $this->length) {
            $char = $this->source[$this->cursor];

            // Skip whitespace
            if ($char === ' ' || $char === "\t") {
                $this->cursor++;
                continue;
            }

            $token = match ($char) {
                '(' => $this->single(TokenType::ParenOpen),
                ')' => $this->single(TokenType::ParenClose),
                ':' => $this->single(TokenType::Colon),
                ',' => $this->single(TokenType::Comma),
                '.' => $this->single(TokenType::Dot),
                '=' => $this->single(TokenType::Equals),
                default => null,
            };

            if ($token !== null) {
                $tokens[] = $token;
                continue;
            }

            // Identifier: letters, digits, underscores, hyphens, pipes
            if ($this->isIdentifierStart($char)) {
                $tokens[] = $this->readIdentifier();
                continue;
            }

            throw new SyntaxException(
                "Unexpected character '{$char}'",
                $this->cursor,
                $this->source,
            );
        }

        $tokens[] = new Token(TokenType::Eof, '', $this->cursor);

        return $tokens;
    }

    /**
     * Create a single-character token and advance cursor.
     */
    private function single(TokenType $type): Token
    {
        $token = new Token($type, $this->source[$this->cursor], $this->cursor);
        $this->cursor++;
        return $token;
    }

    /**
     * Read a contiguous identifier (alphanumeric + underscore + hyphen + pipe).
     *
     * Pipe `|` is used as a value separator within select operations
     * to avoid ambiguity with commas separating operations.
     */
    private function readIdentifier(): Token
    {
        $start = $this->cursor;

        while ($this->cursor < $this->length && $this->isIdentifierChar($this->source[$this->cursor])) {
            $this->cursor++;
        }

        $value = substr($this->source, $start, $this->cursor - $start);

        return new Token(TokenType::Identifier, $value, $start);
    }

    private function isIdentifierStart(string $char): bool
    {
        return ctype_alnum($char) || $char === '_';
    }

    private function isIdentifierChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '-' || $char === '|';
    }
}
