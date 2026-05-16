<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\IncludeQuery;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Database\IncludeQuery\Exceptions\InvalidOperationValueException;
use YasserElgammal\Green\Database\IncludeQuery\Exceptions\UnknownOperationException;
use YasserElgammal\Green\Database\IncludeQuery\Operations\LimitOperation;
use YasserElgammal\Green\Database\IncludeQuery\Operations\OperationInterface;
use YasserElgammal\Green\Database\IncludeQuery\Operations\OperationRegistry;
use YasserElgammal\Green\Database\IncludeQuery\Operations\OrderOperation;
use YasserElgammal\Green\Database\IncludeQuery\Operations\SelectOperation;

/**
 * @covers \YasserElgammal\Green\Database\IncludeQuery\Operations\OperationRegistry
 * @covers \YasserElgammal\Green\Database\IncludeQuery\Operations\LimitOperation
 * @covers \YasserElgammal\Green\Database\IncludeQuery\Operations\OrderOperation
 * @covers \YasserElgammal\Green\Database\IncludeQuery\Operations\SelectOperation
 */
class OperationRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        OperationRegistry::reset();
    }

    protected function tearDown(): void
    {
        OperationRegistry::reset();
    }

    // ── Registry ──────────────────────────────────────────────────────────────

    public function test_resolve_known_operations(): void
    {
        $this->assertInstanceOf(LimitOperation::class, OperationRegistry::resolve('limit'));
        $this->assertInstanceOf(OrderOperation::class, OperationRegistry::resolve('order'));
        $this->assertInstanceOf(SelectOperation::class, OperationRegistry::resolve('select'));
    }

    public function test_resolve_unknown_operation_throws(): void
    {
        $this->expectException(UnknownOperationException::class);
        $this->expectExceptionMessageMatches('/Unknown operation \[foo\]/');

        OperationRegistry::resolve('foo', 'comments');
    }

    public function test_has_operation(): void
    {
        $this->assertTrue(OperationRegistry::has('limit'));
        $this->assertTrue(OperationRegistry::has('order'));
        $this->assertFalse(OperationRegistry::has('nonexistent'));
    }

    public function test_names_returns_all_registered(): void
    {
        $names = OperationRegistry::names();

        $this->assertContains('limit', $names);
        $this->assertContains('order', $names);
        $this->assertContains('select', $names);
        $this->assertContains('filter', $names);
        $this->assertContains('offset', $names);
    }

    public function test_register_custom_operation(): void
    {
        OperationRegistry::register('custom', LimitOperation::class);

        $this->assertTrue(OperationRegistry::has('custom'));
        $this->assertInstanceOf(LimitOperation::class, OperationRegistry::resolve('custom'));
    }

    public function test_register_invalid_class_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OperationRegistry::register('bad', \stdClass::class);
    }

    // ── Limit validation ──────────────────────────────────────────────────────

    public function test_limit_validates_positive_integer(): void
    {
        $limit = new LimitOperation();

        // Valid
        $limit->validate('5');
        $limit->validate('100');

        $this->assertTrue(true); // No exception = pass
    }

    public function test_limit_rejects_non_integer(): void
    {
        $this->expectException(InvalidOperationValueException::class);

        (new LimitOperation())->validate('abc');
    }

    public function test_limit_rejects_zero(): void
    {
        $this->expectException(InvalidOperationValueException::class);

        (new LimitOperation())->validate('0');
    }

    public function test_limit_rejects_negative(): void
    {
        $this->expectException(InvalidOperationValueException::class);

        (new LimitOperation())->validate('-5');
    }

    // ── Order validation ──────────────────────────────────────────────────────

    public function test_order_validates_simple_direction(): void
    {
        $order = new OrderOperation();

        $order->validate('asc');
        $order->validate('desc');
        $order->validate('ASC');
        $order->validate('DESC');

        $this->assertTrue(true);
    }

    public function test_order_validates_column_direction(): void
    {
        $order = new OrderOperation();

        $order->validate('created_at|desc');
        $order->validate('name|asc');

        $this->assertTrue(true);
    }

    public function test_order_rejects_invalid_direction(): void
    {
        $this->expectException(InvalidOperationValueException::class);

        (new OrderOperation())->validate('sideways');
    }

    // ── Select validation ─────────────────────────────────────────────────────

    public function test_select_validates_column_names(): void
    {
        $select = new SelectOperation();

        $select->validate('id');
        $select->validate('id|name|email');

        $this->assertTrue(true);
    }

    public function test_select_rejects_empty(): void
    {
        $this->expectException(InvalidOperationValueException::class);

        (new SelectOperation())->validate('');
    }

    public function test_select_rejects_invalid_column_name(): void
    {
        $this->expectException(InvalidOperationValueException::class);

        (new SelectOperation())->validate('id|123bad');
    }
}
