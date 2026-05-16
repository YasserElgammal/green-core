<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\IncludeQuery;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Database\IncludeQuery\Parser\Token;
use YasserElgammal\Green\Database\IncludeQuery\Parser\Tokenizer;
use YasserElgammal\Green\Database\IncludeQuery\Parser\TokenType;
use YasserElgammal\Green\Database\IncludeQuery\Exceptions\SyntaxException;

/**
 * @covers \YasserElgammal\Green\Database\IncludeQuery\Parser\Tokenizer
 */
class TokenizerTest extends TestCase
{
    private Tokenizer $tokenizer;

    protected function setUp(): void
    {
        $this->tokenizer = new Tokenizer();
    }

    public function test_empty_string_returns_eof_only(): void
    {
        $tokens = $this->tokenizer->tokenize('');

        $this->assertCount(1, $tokens);
        $this->assertTrue($tokens[0]->is(TokenType::Eof));
    }

    public function test_simple_identifier(): void
    {
        $tokens = $this->tokenizer->tokenize('comments');

        $this->assertCount(2, $tokens);
        $this->assertToken($tokens[0], TokenType::Identifier, 'comments');
        $this->assertTrue($tokens[1]->is(TokenType::Eof));
    }

    public function test_dot_separated_identifiers(): void
    {
        $tokens = $this->tokenizer->tokenize('comments.author');

        $this->assertCount(4, $tokens);
        $this->assertToken($tokens[0], TokenType::Identifier, 'comments');
        $this->assertToken($tokens[1], TokenType::Dot, '.');
        $this->assertToken($tokens[2], TokenType::Identifier, 'author');
        $this->assertTrue($tokens[3]->is(TokenType::Eof));
    }

    public function test_relation_with_operations(): void
    {
        $tokens = $this->tokenizer->tokenize('comments(limit:5)');

        $types = array_map(fn(Token $t) => $t->type, $tokens);

        $this->assertEquals([
            TokenType::Identifier,  // comments
            TokenType::ParenOpen,   // (
            TokenType::Identifier,  // limit
            TokenType::Colon,       // :
            TokenType::Identifier,  // 5
            TokenType::ParenClose,  // )
            TokenType::Eof,
        ], $types);
    }

    public function test_multiple_operations(): void
    {
        $tokens = $this->tokenizer->tokenize('posts(limit:5,order:desc)');

        $types = array_map(fn(Token $t) => $t->type, $tokens);

        $this->assertEquals([
            TokenType::Identifier,  // posts
            TokenType::ParenOpen,   // (
            TokenType::Identifier,  // limit
            TokenType::Colon,       // :
            TokenType::Identifier,  // 5
            TokenType::Comma,       // ,
            TokenType::Identifier,  // order
            TokenType::Colon,       // :
            TokenType::Identifier,  // desc
            TokenType::ParenClose,  // )
            TokenType::Eof,
        ], $types);
    }

    public function test_pipe_in_identifier(): void
    {
        $tokens = $this->tokenizer->tokenize('select:id|name');

        $this->assertToken($tokens[2], TokenType::Identifier, 'id|name');
    }

    public function test_filter_with_equals(): void
    {
        $tokens = $this->tokenizer->tokenize('filter:status=active');

        $types = array_map(fn(Token $t) => $t->type, $tokens);

        $this->assertContains(TokenType::Equals, $types);
    }

    public function test_whitespace_is_skipped(): void
    {
        $tokens = $this->tokenizer->tokenize('comments ( limit : 5 )');

        $nonEof = array_filter($tokens, fn(Token $t) => !$t->is(TokenType::Eof));

        $this->assertCount(6, $nonEof);
    }

    public function test_unexpected_character_throws_syntax_exception(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessageMatches('/Unexpected character/');

        $this->tokenizer->tokenize('comments[limit]');
    }

    public function test_position_tracking(): void
    {
        $tokens = $this->tokenizer->tokenize('ab.cd');

        $this->assertEquals(0, $tokens[0]->position); // 'ab' starts at 0
        $this->assertEquals(2, $tokens[1]->position); // '.' at 2
        $this->assertEquals(3, $tokens[2]->position); // 'cd' starts at 3
    }

    public function test_complex_nested_expression(): void
    {
        $tokens = $this->tokenizer->tokenize(
            'comments(limit:5,order:desc).author(select:id|name)'
        );

        // Should produce valid token stream without errors
        $this->assertGreaterThan(10, count($tokens));
        $this->assertTrue(end($tokens)->is(TokenType::Eof));
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function assertToken(Token $token, TokenType $type, string $value): void
    {
        $this->assertEquals($type, $token->type, "Expected token type {$type->value}");
        $this->assertEquals($value, $token->value, "Expected token value '{$value}'");
    }
}
