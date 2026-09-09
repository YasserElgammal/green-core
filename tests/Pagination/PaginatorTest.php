<?php

namespace YasserElgammal\Green\Tests\Pagination;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Pagination\Paginator;

final class PaginatorTest extends TestCase
{
    public function test_counted_array_pagination_clamps_page_and_reports_totals(): void
    {
        $result = (new Paginator())->paginate(range(1, 5), 2, 99);

        self::assertSame([5], $result['data']);
        self::assertSame([
            'current_page' => 3,
            'per_page' => 2,
            'total_items' => 5,
            'total_pages' => 3,
            'has_next' => false,
            'has_prev' => true,
        ], $result['meta']);
    }

    public function test_uncounted_pagination_uses_lookahead_for_next_page(): void
    {
        $result = (new Paginator())->paginate(range(1, 5), 2, 2, false);

        self::assertSame([3, 4], $result['data']);
        self::assertNull($result['meta']['total_items']);
        self::assertNull($result['meta']['total_pages']);
        self::assertTrue($result['meta']['has_next']);
        self::assertTrue($result['meta']['has_prev']);
    }

    public function test_invalid_page_size_and_page_are_normalized(): void
    {
        $result = (new Paginator())->paginate(['first', 'second'], 0, 0);

        self::assertSame(['first'], $result['data']);
        self::assertSame(1, $result['meta']['current_page']);
        self::assertSame(1, $result['meta']['per_page']);
    }
}
