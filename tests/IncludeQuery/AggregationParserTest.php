<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\IncludeQuery;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Database\IncludeQuery\Aggregations\AggregationRegistry;
use YasserElgammal\Green\Database\IncludeQuery\Parser\IncludeParser;

/**
 * Tests for parser support of value-less operations (aggregation syntax)
 * and mixed aggregation + constraint operations.
 */
class AggregationParserTest extends TestCase
{
    private IncludeParser $parser;

    protected function setUp(): void
    {
        $this->parser = new IncludeParser();
        AggregationRegistry::reset();
    }

    // ─── Value-less operations ────────────────────────────────────────────────

    public function test_parse_single_valueless_operation(): void
    {
        $nodes = $this->parser->parse('comments(count)');

        $this->assertCount(1, $nodes);
        $this->assertSame('comments', $nodes[0]->relation);
        $this->assertTrue($nodes[0]->hasOperations());

        $ops = $nodes[0]->operations;
        $this->assertSame(1, $ops->count());
        $this->assertTrue($ops->has('count'));

        $countOp = $ops->get('count');
        $this->assertSame('count', $countOp->name);
        $this->assertSame('', $countOp->rawValue);
        $this->assertFalse($countOp->hasValue());
    }

    public function test_parse_exists_operation(): void
    {
        $nodes = $this->parser->parse('comments(exists)');

        $this->assertCount(1, $nodes);
        $ops = $nodes[0]->operations;
        $this->assertTrue($ops->has('exists'));
        $this->assertSame('', $ops->get('exists')->rawValue);
    }

    // ─── Mixed operations ─────────────────────────────────────────────────────

    public function test_parse_mixed_valueless_and_valued(): void
    {
        $nodes = $this->parser->parse('comments(limit:5,count)');

        $this->assertCount(1, $nodes);
        $ops = $nodes[0]->operations;
        $this->assertSame(2, $ops->count());

        // limit:5 → valued
        $this->assertTrue($ops->has('limit'));
        $this->assertSame('5', $ops->get('limit')->rawValue);
        $this->assertTrue($ops->get('limit')->hasValue());

        // count → value-less
        $this->assertTrue($ops->has('count'));
        $this->assertSame('', $ops->get('count')->rawValue);
        $this->assertFalse($ops->get('count')->hasValue());
    }

    public function test_parse_valueless_before_valued(): void
    {
        $nodes = $this->parser->parse('comments(count,limit:10)');

        $ops = $nodes[0]->operations;
        $this->assertTrue($ops->has('count'));
        $this->assertTrue($ops->has('limit'));
        $this->assertSame('10', $ops->get('limit')->rawValue);
    }

    public function test_parse_multiple_valued_aggregations(): void
    {
        $nodes = $this->parser->parse('orders(sum:price,avg:price,min:price,max:price)');

        $ops = $nodes[0]->operations;
        $this->assertSame(4, $ops->count());

        $this->assertSame('price', $ops->get('sum')->rawValue);
        $this->assertSame('price', $ops->get('avg')->rawValue);
        $this->assertSame('price', $ops->get('min')->rawValue);
        $this->assertSame('price', $ops->get('max')->rawValue);
    }

    public function test_parse_count_and_avg_mixed(): void
    {
        $nodes = $this->parser->parse('comments(count,avg:rating)');

        $ops = $nodes[0]->operations;
        $this->assertSame(2, $ops->count());
        $this->assertFalse($ops->get('count')->hasValue());
        $this->assertSame('rating', $ops->get('avg')->rawValue);
    }

    // ─── Nested with aggregations ─────────────────────────────────────────────

    public function test_parse_nested_with_aggregation(): void
    {
        $nodes = $this->parser->parse('comments(count).author(select:id|name)');

        $this->assertCount(1, $nodes);
        $this->assertSame('comments', $nodes[0]->relation);
        $this->assertTrue($nodes[0]->operations->has('count'));
        $this->assertTrue($nodes[0]->hasChild());
        $this->assertSame('author', $nodes[0]->child->relation);
        $this->assertTrue($nodes[0]->child->operations->has('select'));
    }

    // ─── Multiple top-level with aggregations ─────────────────────────────────

    public function test_parse_comma_separated_with_aggregations(): void
    {
        $nodes = $this->parser->parse('comments(count),likes(count)');

        $this->assertCount(2, $nodes);
        $this->assertSame('comments', $nodes[0]->relation);
        $this->assertTrue($nodes[0]->operations->has('count'));
        $this->assertSame('likes', $nodes[1]->relation);
        $this->assertTrue($nodes[1]->operations->has('count'));
    }

    // ─── OperationBag aggregation helpers ─────────────────────────────────────

    public function test_operation_bag_get_aggregations(): void
    {
        $nodes = $this->parser->parse('comments(limit:5,count,avg:rating)');
        $ops = $nodes[0]->operations;

        $aggregations = $ops->getAggregations();
        $this->assertCount(2, $aggregations);

        $names = array_map(fn($op) => $op->name, $aggregations);
        $this->assertContains('count', $names);
        $this->assertContains('avg', $names);
    }

    public function test_operation_bag_get_non_aggregations(): void
    {
        $nodes = $this->parser->parse('comments(limit:5,count,order:desc)');
        $ops = $nodes[0]->operations;

        $nonAggs = $ops->getNonAggregations();
        $this->assertCount(2, $nonAggs);

        $names = array_map(fn($op) => $op->name, $nonAggs);
        $this->assertContains('limit', $names);
        $this->assertContains('order', $names);
    }

    public function test_operation_bag_has_aggregations(): void
    {
        $nodesWithAgg = $this->parser->parse('comments(count)');
        $this->assertTrue($nodesWithAgg[0]->operations->hasAggregations());

        $nodesWithout = $this->parser->parse('comments(limit:5)');
        $this->assertFalse($nodesWithout[0]->operations->hasAggregations());
    }

    // ─── Backward compatibility ───────────────────────────────────────────────

    public function test_existing_valued_operations_still_work(): void
    {
        // Ensure the parser change doesn't break existing syntax
        $nodes = $this->parser->parse('comments(limit:5,order:desc,select:id|name)');

        $ops = $nodes[0]->operations;
        $this->assertSame(3, $ops->count());
        $this->assertSame('5', $ops->get('limit')->rawValue);
        $this->assertSame('desc', $ops->get('order')->rawValue);
        $this->assertSame('id|name', $ops->get('select')->rawValue);
    }

    public function test_filter_operation_still_works(): void
    {
        $nodes = $this->parser->parse('comments(filter:status=active)');

        $ops = $nodes[0]->operations;
        $this->assertSame('status=active', $ops->get('filter')->rawValue);
    }

    public function test_nested_without_aggregations_still_works(): void
    {
        $nodes = $this->parser->parse('comments(limit:5).author(select:id|name)');

        $this->assertSame('comments', $nodes[0]->relation);
        $this->assertSame('5', $nodes[0]->operations->get('limit')->rawValue);
        $this->assertTrue($nodes[0]->hasChild());
        $this->assertSame('author', $nodes[0]->child->relation);
    }
}
