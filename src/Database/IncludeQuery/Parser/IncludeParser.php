<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Parser;

use YasserElgammal\Green\Database\IncludeQuery\Ast\IncludeNode;
use YasserElgammal\Green\Database\IncludeQuery\Ast\Operation;
use YasserElgammal\Green\Database\IncludeQuery\Ast\OperationBag;
use YasserElgammal\Green\Database\IncludeQuery\Exceptions\SyntaxException;

/**
 * Recursive descent parser for the Include Query Language.
 *
 * Consumes a token stream (from Tokenizer) and produces an array of IncludeNode ASTs.
 *
 * Grammar:
 *   include_list  → include_expr (',' include_expr)*
 *   include_expr  → relation ('.' relation)*
 *   relation      → IDENTIFIER ('(' operations ')')?
 *   operations    → operation (',' operation)*
 *   operation     → IDENTIFIER ':' value
 *   value         → IDENTIFIER                          // simple: limit:5
 *                  | IDENTIFIER '=' IDENTIFIER           // filter: status=active
 *
 * Public API:
 *   $parser = new IncludeParser();
 *   $nodes  = $parser->parse('comments(limit:5).author(select:id|name),roles');
 */
final class IncludeParser
{
    /** @var Token[] */
    private array $tokens;
    private int   $position;
    private string $source;

    /**
     * Parse a raw include query string into an array of IncludeNode ASTs.
     *
     * @param  string  $input  e.g. 'comments(limit:5,order:desc).author(select:id|name)'
     * @return IncludeNode[]
     *
     * @throws SyntaxException
     */
    public function parse(string $input): array
    {
        $input = trim($input);

        if ($input === '') {
            return [];
        }

        $this->source   = $input;
        $this->tokens   = (new Tokenizer())->tokenize($input);
        $this->position = 0;

        $nodes = $this->parseIncludeList();

        // Ensure we consumed all tokens
        if (!$this->current()->is(TokenType::Eof)) {
            throw new SyntaxException(
                "Unexpected token '{$this->current()->value}' — expected end of input",
                $this->current()->position,
                $this->source,
            );
        }

        return $nodes;
    }

    // ─── Grammar rules ───────────────────────────────────────────────────────

    /**
     * include_list → include_expr (',' include_expr)*
     *
     * @return IncludeNode[]
     */
    private function parseIncludeList(): array
    {
        $nodes = [$this->parseIncludeExpr()];

        while ($this->current()->is(TokenType::Comma)) {
            $this->advance(); // consume ','
            $nodes[] = $this->parseIncludeExpr();
        }

        return $nodes;
    }

    /**
     * include_expr → relation ('.' relation)*
     *
     * Builds a linked list of IncludeNodes from innermost to outermost.
     */
    private function parseIncludeExpr(): IncludeNode
    {
        // Collect all segments: [relation1, relation2, ...]
        $segments = [$this->parseRelation()];

        while ($this->current()->is(TokenType::Dot)) {
            $this->advance(); // consume '.'
            $segments[] = $this->parseRelation();
        }

        // Build the linked list from the tail (innermost child has no child)
        $node = null;
        for ($i = count($segments) - 1; $i >= 0; $i--) {
            [$name, $operations] = $segments[$i];
            $node = new IncludeNode($name, $operations, $node);
        }

        /** @var IncludeNode $node — guaranteed non-null since $segments is non-empty */
        return $node;
    }

    /**
     * relation → IDENTIFIER ('(' operations ')')?
     *
     * @return array{0: string, 1: OperationBag}
     */
    private function parseRelation(): array
    {
        $name = $this->expect(TokenType::Identifier, 'relation name')->value;

        $operations = new OperationBag();

        if ($this->current()->is(TokenType::ParenOpen)) {
            $this->advance(); // consume '('
            $operations = $this->parseOperations();
            $this->expect(TokenType::ParenClose, "closing ')' for relation '{$name}'");
        }

        return [$name, $operations];
    }

    /**
     * operations → operation (',' operation)*
     */
    private function parseOperations(): OperationBag
    {
        $ops = [$this->parseOperation()];

        while ($this->current()->is(TokenType::Comma)) {
            $this->advance(); // consume ','
            $ops[] = $this->parseOperation();
        }

        return new OperationBag($ops);
    }

    /**
     * operation → IDENTIFIER ':' value
     */
    private function parseOperation(): Operation
    {
        $name = $this->expect(TokenType::Identifier, 'operation name')->value;
        $this->expect(TokenType::Colon, "':' after operation name '{$name}'");
        $value = $this->parseValue();

        return new Operation($name, $value);
    }

    /**
     * value → IDENTIFIER ('=' IDENTIFIER)?
     *
     * Handles both simple values (limit:5) and filter values (filter:status=active).
     */
    private function parseValue(): string
    {
        $value = $this->expect(TokenType::Identifier, 'operation value')->value;

        // Handle filter syntax: status=active
        if ($this->current()->is(TokenType::Equals)) {
            $this->advance(); // consume '='
            $rhs = $this->expect(TokenType::Identifier, 'filter value')->value;
            $value = $value . '=' . $rhs;
        }

        return $value;
    }

    // ─── Token helpers ───────────────────────────────────────────────────────

    /**
     * Return the current token without consuming it.
     */
    private function current(): Token
    {
        return $this->tokens[$this->position];
    }

    /**
     * Advance to the next token and return the previous one.
     */
    private function advance(): Token
    {
        $token = $this->tokens[$this->position];
        $this->position++;
        return $token;
    }

    /**
     * Assert the current token is of the expected type, consume and return it.
     *
     * @throws SyntaxException
     */
    private function expect(TokenType $type, string $description): Token
    {
        $token = $this->current();

        if (!$token->is($type)) {
            throw new SyntaxException(
                "Expected {$description} ({$type->value}), got '{$token->value}' ({$token->type->value})",
                $token->position,
                $this->source,
            );
        }

        return $this->advance();
    }
}
