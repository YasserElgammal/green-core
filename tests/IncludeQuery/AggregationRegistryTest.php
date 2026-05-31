<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\IncludeQuery;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Database\IncludeQuery\Aggregations\AggregationInterface;
use YasserElgammal\Green\Database\IncludeQuery\Aggregations\AggregationRegistry;
use YasserElgammal\Green\Database\IncludeQuery\Aggregations\AvgAggregation;
use YasserElgammal\Green\Database\IncludeQuery\Aggregations\CountAggregation;
use YasserElgammal\Green\Database\IncludeQuery\Aggregations\ExistsAggregation;
use YasserElgammal\Green\Database\IncludeQuery\Aggregations\MaxAggregation;
use YasserElgammal\Green\Database\IncludeQuery\Aggregations\MinAggregation;
use YasserElgammal\Green\Database\IncludeQuery\Aggregations\SumAggregation;
use YasserElgammal\Green\Database\IncludeQuery\Exceptions\InvalidOperationValueException;

/**
 * Tests for the AggregationRegistry and concrete AggregationInterface implementations.
 */
class AggregationRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        AggregationRegistry::reset();
    }

    // ─── Registry ─────────────────────────────────────────────────────────────

    public function test_resolve_returns_correct_instances(): void
    {
        $this->assertInstanceOf(CountAggregation::class, AggregationRegistry::resolve('count'));
        $this->assertInstanceOf(ExistsAggregation::class, AggregationRegistry::resolve('exists'));
        $this->assertInstanceOf(SumAggregation::class, AggregationRegistry::resolve('sum'));
        $this->assertInstanceOf(AvgAggregation::class, AggregationRegistry::resolve('avg'));
        $this->assertInstanceOf(MinAggregation::class, AggregationRegistry::resolve('min'));
        $this->assertInstanceOf(MaxAggregation::class, AggregationRegistry::resolve('max'));
    }

    public function test_resolve_unknown_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown aggregation [median]');

        AggregationRegistry::resolve('median');
    }

    public function test_has_returns_correct_values(): void
    {
        $this->assertTrue(AggregationRegistry::has('count'));
        $this->assertTrue(AggregationRegistry::has('exists'));
        $this->assertTrue(AggregationRegistry::has('sum'));
        $this->assertTrue(AggregationRegistry::has('avg'));
        $this->assertTrue(AggregationRegistry::has('min'));
        $this->assertTrue(AggregationRegistry::has('max'));
        $this->assertFalse(AggregationRegistry::has('median'));
        $this->assertFalse(AggregationRegistry::has('percentile'));
    }

    public function test_names_returns_all_registered(): void
    {
        $names = AggregationRegistry::names();

        $this->assertContains('count', $names);
        $this->assertContains('exists', $names);
        $this->assertContains('sum', $names);
        $this->assertContains('avg', $names);
        $this->assertContains('min', $names);
        $this->assertContains('max', $names);
        $this->assertCount(6, $names);
    }

    public function test_register_custom_aggregation(): void
    {
        $custom = new class implements AggregationInterface {
            public function name(): string { return 'median'; }
            public function requiresColumn(): bool { return true; }
            public function validate(string $value): void {}
            public function sqlExpression(string $column): string { return "MEDIAN({$column})"; }
            public function attributeName(string $relation, string $column): string { return "{$relation}_median_{$column}"; }
            public function castResult(mixed $raw): mixed { return (float) ($raw ?? 0); }
        };

        AggregationRegistry::register('median', $custom::class);

        $this->assertTrue(AggregationRegistry::has('median'));
        $this->assertInstanceOf($custom::class, AggregationRegistry::resolve('median'));
    }

    public function test_register_invalid_class_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        AggregationRegistry::register('bad', \stdClass::class);
    }

    public function test_reset_clears_custom_aggregations(): void
    {
        $custom = new class implements AggregationInterface {
            public function name(): string { return 'custom'; }
            public function requiresColumn(): bool { return false; }
            public function validate(string $value): void {}
            public function sqlExpression(string $column): string { return 'CUSTOM()'; }
            public function attributeName(string $relation, string $column): string { return "{$relation}_custom"; }
            public function castResult(mixed $raw): mixed { return $raw; }
        };

        AggregationRegistry::register('custom', $custom::class);
        $this->assertTrue(AggregationRegistry::has('custom'));

        AggregationRegistry::reset();
        $this->assertFalse(AggregationRegistry::has('custom'));
        $this->assertCount(6, AggregationRegistry::names());
    }

    // ─── CountAggregation ─────────────────────────────────────────────────────

    public function test_count_name(): void
    {
        $count = new CountAggregation();
        $this->assertSame('count', $count->name());
    }

    public function test_count_does_not_require_column(): void
    {
        $count = new CountAggregation();
        $this->assertFalse($count->requiresColumn());
    }

    public function test_count_validates_empty_value(): void
    {
        $count = new CountAggregation();
        $count->validate(''); // Should not throw
        $this->assertTrue(true);
    }

    public function test_count_rejects_non_empty_value(): void
    {
        $count = new CountAggregation();

        $this->expectException(InvalidOperationValueException::class);
        $count->validate('rating');
    }

    public function test_count_sql_expression(): void
    {
        $count = new CountAggregation();
        $this->assertSame('COUNT(*)', $count->sqlExpression(''));
    }

    public function test_count_attribute_name(): void
    {
        $count = new CountAggregation();
        $this->assertSame('comments_count', $count->attributeName('comments', ''));
        $this->assertSame('likes_count', $count->attributeName('likes', ''));
    }

    public function test_count_cast_result(): void
    {
        $count = new CountAggregation();
        $this->assertSame(5, $count->castResult('5'));
        $this->assertSame(0, $count->castResult(null));
        $this->assertSame(0, $count->castResult('0'));
    }

    // ─── ExistsAggregation ────────────────────────────────────────────────────

    public function test_exists_name(): void
    {
        $exists = new ExistsAggregation();
        $this->assertSame('exists', $exists->name());
    }

    public function test_exists_does_not_require_column(): void
    {
        $exists = new ExistsAggregation();
        $this->assertFalse($exists->requiresColumn());
    }

    public function test_exists_validates_empty_value(): void
    {
        $exists = new ExistsAggregation();
        $exists->validate(''); // Should not throw
        $this->assertTrue(true);
    }

    public function test_exists_rejects_non_empty_value(): void
    {
        $exists = new ExistsAggregation();

        $this->expectException(InvalidOperationValueException::class);
        $exists->validate('foo');
    }

    public function test_exists_attribute_name(): void
    {
        $exists = new ExistsAggregation();
        $this->assertSame('comments_exists', $exists->attributeName('comments', ''));
    }

    public function test_exists_cast_result(): void
    {
        $exists = new ExistsAggregation();
        $this->assertTrue($exists->castResult('1'));
        $this->assertTrue($exists->castResult('5'));
        $this->assertFalse($exists->castResult('0'));
        $this->assertFalse($exists->castResult(null));
    }

    // ─── SumAggregation ───────────────────────────────────────────────────────

    public function test_sum_requires_column(): void
    {
        $sum = new SumAggregation();
        $this->assertTrue($sum->requiresColumn());
    }

    public function test_sum_validates_column_name(): void
    {
        $sum = new SumAggregation();
        $sum->validate('total'); // Should not throw
        $sum->validate('order_total'); // Should not throw
        $this->assertTrue(true);
    }

    public function test_sum_rejects_empty_column(): void
    {
        $sum = new SumAggregation();

        $this->expectException(InvalidOperationValueException::class);
        $sum->validate('');
    }

    public function test_sum_rejects_invalid_column(): void
    {
        $sum = new SumAggregation();

        $this->expectException(InvalidOperationValueException::class);
        $sum->validate('1invalid');
    }

    public function test_sum_sql_expression(): void
    {
        $sum = new SumAggregation();
        $this->assertSame('SUM(total)', $sum->sqlExpression('total'));
        $this->assertSame('SUM(price)', $sum->sqlExpression('price'));
    }

    public function test_sum_attribute_name(): void
    {
        $sum = new SumAggregation();
        $this->assertSame('orders_sum_total', $sum->attributeName('orders', 'total'));
        $this->assertSame('orders_sum_price', $sum->attributeName('orders', 'price'));
    }

    public function test_sum_cast_result(): void
    {
        $sum = new SumAggregation();
        $this->assertSame(99.5, $sum->castResult('99.5'));
        $this->assertSame(0.0, $sum->castResult(null));
    }

    // ─── AvgAggregation ───────────────────────────────────────────────────────

    public function test_avg_requires_column(): void
    {
        $avg = new AvgAggregation();
        $this->assertTrue($avg->requiresColumn());
    }

    public function test_avg_sql_expression(): void
    {
        $avg = new AvgAggregation();
        $this->assertSame('AVG(rating)', $avg->sqlExpression('rating'));
    }

    public function test_avg_attribute_name(): void
    {
        $avg = new AvgAggregation();
        $this->assertSame('reviews_avg_rating', $avg->attributeName('reviews', 'rating'));
    }

    public function test_avg_cast_result(): void
    {
        $avg = new AvgAggregation();
        $this->assertSame(4.5, $avg->castResult('4.5'));
        $this->assertNull($avg->castResult(null));
    }

    // ─── MinAggregation ───────────────────────────────────────────────────────

    public function test_min_requires_column(): void
    {
        $min = new MinAggregation();
        $this->assertTrue($min->requiresColumn());
    }

    public function test_min_sql_expression(): void
    {
        $min = new MinAggregation();
        $this->assertSame('MIN(price)', $min->sqlExpression('price'));
    }

    public function test_min_attribute_name(): void
    {
        $min = new MinAggregation();
        $this->assertSame('orders_min_price', $min->attributeName('orders', 'price'));
    }

    public function test_min_cast_result(): void
    {
        $min = new MinAggregation();
        $this->assertSame('10', $min->castResult('10'));
        $this->assertNull($min->castResult(null));
    }

    // ─── MaxAggregation ───────────────────────────────────────────────────────

    public function test_max_requires_column(): void
    {
        $max = new MaxAggregation();
        $this->assertTrue($max->requiresColumn());
    }

    public function test_max_sql_expression(): void
    {
        $max = new MaxAggregation();
        $this->assertSame('MAX(price)', $max->sqlExpression('price'));
    }

    public function test_max_attribute_name(): void
    {
        $max = new MaxAggregation();
        $this->assertSame('orders_max_price', $max->attributeName('orders', 'price'));
    }

    public function test_max_rejects_empty_column(): void
    {
        $max = new MaxAggregation();

        $this->expectException(InvalidOperationValueException::class);
        $max->validate('');
    }
}
