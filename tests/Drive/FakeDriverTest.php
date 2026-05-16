<?php

namespace YasserElgammal\Green\Tests\Drive;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Drive\Drivers\FakeDriver;

class FakeDriverTest extends TestCase
{
    private FakeDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new FakeDriver();
    }

    public function test_it_can_put_and_get()
    {
        $this->driver->put('a.txt', 'hello');
        $this->assertTrue($this->driver->exists('a.txt'));
        $this->assertEquals('hello', $this->driver->get('a.txt'));
    }

    public function test_assertions_work()
    {
        $this->driver->put('b.txt', 'test');
        
        $this->driver->assertExists('b.txt');
        $this->driver->assertMissing('c.txt');
        $this->driver->assertContentEquals('b.txt', 'test');
        $this->driver->assertCount(1);
    }

    public function test_it_handles_directories()
    {
        $this->driver->put('a/b/c.txt', 'data');
        
        $this->assertEquals(['a'], $this->driver->directories());
        $this->assertEquals(['a/b'], $this->driver->directories('a'));
        
        $this->driver->deleteDirectory('a');
        $this->assertFalse($this->driver->exists('a/b/c.txt'));
    }
}
