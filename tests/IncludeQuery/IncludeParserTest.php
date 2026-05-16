<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\IncludeQuery;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Database\IncludeQuery\Ast\IncludeNode;
use YasserElgammal\Green\Database\IncludeQuery\Exceptions\SyntaxException;
use YasserElgammal\Green\Database\IncludeQuery\Parser\IncludeParser;

/**
 * @covers \YasserElgammal\Green\Database\IncludeQuery\Parser\IncludeParser
 */
class IncludeParserTest extends TestCase
{
    private IncludeParser $parser;

    protected function setUp(): void
    {
        $this->parser = new IncludeParser();
    }

    // ── Basic parsing ─────────────────────────────────────────────────────────

    public function test_empty_string_returns_empty_array(): void
    {
        $nodes = $this->parser->parse('');
        $this->assertEmpty($nodes);
    }

    public function test_single_relation_no_operations(): void
    {
        $nodes = $this->parser->parse('comments');

        $this->assertCount(1, $nodes);
        $this->assertEquals('comments', $nodes[0]->relation);
        $this->assertFalse($nodes[0]->hasOperations());
        $this->assertFalse($nodes[0]->hasChild());
    }

    public function test_multiple_relations_comma_separated(): void
    {
        $nodes = $this->parser->parse('comments,roles,tags');

        $this->assertCount(3, $nodes);
        $this->assertEquals('comments', $nodes[0]->relation);
        $this->assertEquals('roles', $nodes[1]->relation);
        $this->assertEquals('tags', $nodes[2]->relation);
    }

    // ── Operations ────────────────────────────────────────────────────────────

    public function test_single_operation(): void
    {
        $nodes = $this->parser->parse('comments(limit:5)');

        $this->assertCount(1, $nodes);
        $node = $nodes[0];

        $this->assertEquals('comments', $node->relation);
        $this->assertTrue($node->hasOperations());
        $this->assertTrue($node->operations->has('limit'));
        $this->assertEquals('5', $node->operations->get('limit')->rawValue);
    }

    public function test_multiple_operations(): void
    {
        $nodes = $this->parser->parse('comments(limit:5,order:desc)');

        $node = $nodes[0];
        $this->assertEquals(2, $node->operations->count());
        $this->assertEquals('5', $node->operations->get('limit')->rawValue);
        $this->assertEquals('desc', $node->operations->get('order')->rawValue);
    }

    public function test_select_with_pipe_separator(): void
    {
        $nodes = $this->parser->parse('author(select:id|name|email)');

        $op = $nodes[0]->operations->get('select');
        $this->assertNotNull($op);
        $this->assertEquals('id|name|email', $op->rawValue);
        $this->assertEquals(['id', 'name', 'email'], $op->values());
    }

    public function test_filter_with_equals(): void
    {
        $nodes = $this->parser->parse('comments(filter:status=active)');

        $op = $nodes[0]->operations->get('filter');
        $this->assertNotNull($op);
        $this->assertEquals('status=active', $op->rawValue);
    }

    // ── Nesting ───────────────────────────────────────────────────────────────

    public function test_nested_relation(): void
    {
        $nodes = $this->parser->parse('comments.author');

        $this->assertCount(1, $nodes);
        $this->assertEquals('comments', $nodes[0]->relation);
        $this->assertTrue($nodes[0]->hasChild());
        $this->assertEquals('author', $nodes[0]->child->relation);
        $this->assertFalse($nodes[0]->child->hasChild());
    }

    public function test_deeply_nested_relation(): void
    {
        $nodes = $this->parser->parse('comments.author.profile');

        $node = $nodes[0];
        $this->assertEquals('comments', $node->relation);
        $this->assertEquals('author', $node->child->relation);
        $this->assertEquals('profile', $node->child->child->relation);
        $this->assertNull($node->child->child->child);
    }

    public function test_nested_with_operations(): void
    {
        $nodes = $this->parser->parse('comments(limit:5).author(select:id|name)');

        $comments = $nodes[0];
        $author   = $comments->child;

        $this->assertEquals('5', $comments->operations->get('limit')->rawValue);
        $this->assertEquals('id|name', $author->operations->get('select')->rawValue);
    }

    // ── Complex expressions ──────────────────────────────────────────────────

    public function test_mixed_simple_and_advanced(): void
    {
        $nodes = $this->parser->parse('comments(limit:5,order:desc).author(select:id|name),roles');

        $this->assertCount(2, $nodes);

        // First: comments(limit:5,order:desc).author(select:id|name)
        $comments = $nodes[0];
        $this->assertEquals('comments', $comments->relation);
        $this->assertEquals('5', $comments->operations->get('limit')->rawValue);
        $this->assertEquals('desc', $comments->operations->get('order')->rawValue);

        $author = $comments->child;
        $this->assertEquals('author', $author->relation);
        $this->assertEquals('id|name', $author->operations->get('select')->rawValue);

        // Second: roles (simple)
        $this->assertEquals('roles', $nodes[1]->relation);
        $this->assertFalse($nodes[1]->hasOperations());
    }

    public function test_full_complex_expression(): void
    {
        $nodes = $this->parser->parse(
            'comments(limit:5,order:desc,select:id|content).author(select:id|name)'
        );

        $this->assertCount(1, $nodes);

        $comments = $nodes[0];
        $this->assertEquals(3, $comments->operations->count());
        $this->assertEquals('5', $comments->operations->get('limit')->rawValue);
        $this->assertEquals('desc', $comments->operations->get('order')->rawValue);
        $this->assertEquals('id|content', $comments->operations->get('select')->rawValue);
    }

    // ── toArray ──────────────────────────────────────────────────────────────

    public function test_to_array(): void
    {
        $nodes = $this->parser->parse('comments(limit:5).author');

        $array = $nodes[0]->toArray();

        $this->assertEquals('comments', $array['relation']);
        $this->assertEquals(['limit' => '5'], $array['operations']);
        $this->assertEquals('author', $array['child']['relation']);
    }

    public function test_flatten_relations(): void
    {
        $nodes = $this->parser->parse('comments.author.profile');

        $this->assertEquals(
            ['comments', 'author', 'profile'],
            $nodes[0]->flattenRelations()
        );
    }

    // ── Error cases ──────────────────────────────────────────────────────────

    public function test_unclosed_parenthesis_throws(): void
    {
        $this->expectException(SyntaxException::class);
        $this->parser->parse('comments(limit:5');
    }

    public function test_missing_operation_value_throws(): void
    {
        $this->expectException(SyntaxException::class);
        $this->parser->parse('comments(limit:)');
    }

    public function test_missing_colon_throws(): void
    {
        $this->expectException(SyntaxException::class);
        $this->parser->parse('comments(limit 5)');
    }

    public function test_empty_parens_throws(): void
    {
        $this->expectException(SyntaxException::class);
        $this->parser->parse('comments()');
    }

    public function test_trailing_dot_throws(): void
    {
        $this->expectException(SyntaxException::class);
        $this->parser->parse('comments.');
    }
}
