<?php

namespace YasserElgammal\Green\Tests\Cache;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Cache\CacheManager;

class CacheManagerTest extends TestCase
{
    private CacheManager $cache;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = __DIR__ . '/.cache_test';
        $config = [
            'default' => 'array',
            'stores' => [
                'array' => [
                    'driver' => 'array',
                ],
                'file' => [
                    'driver' => 'file',
                    'path' => $this->cacheDir,
                ]
            ]
        ];

        $this->cache = new CacheManager($config);
    }

    protected function tearDown(): void
    {
        $this->cache->store('file')->flush();
        if (is_dir($this->cacheDir)) {
            @rmdir($this->cacheDir);
        }
    }

    public function test_array_driver_puts_and_gets()
    {
        $store = $this->cache->store('array');

        $this->assertNull($store->get('foo'));

        $store->put('foo', 'bar', 10);
        $this->assertEquals('bar', $store->get('foo'));
        $this->assertTrue($store->has('foo'));

        $store->forget('foo');
        $this->assertNull($store->get('foo'));
    }

    public function test_file_driver_puts_and_gets()
    {
        $store = $this->cache->store('file');

        $this->assertNull($store->get('file_key'));

        $store->put('file_key', ['complex' => 'data'], 10);
        $this->assertEquals(['complex' => 'data'], $store->get('file_key'));

        $store->forget('file_key');
        $this->assertNull($store->get('file_key'));
    }

    public function test_manager_proxies_to_default_store()
    {
        $this->cache->put('default_key', 'value', 10);

        $this->assertEquals('value', $this->cache->get('default_key'));
        // It should be in the array store
        $this->assertEquals('value', $this->cache->store('array')->get('default_key'));
    }

    public function test_cache_remember()
    {
        $executed = false;
        $result = $this->cache->remember('remember_key', 10, function() use (&$executed) {
            $executed = true;
            return 'computed';
        });

        $this->assertTrue($executed);
        $this->assertEquals('computed', $result);

        // Second time should not execute closure
        $executed = false;
        $result = $this->cache->remember('remember_key', 10, function() use (&$executed) {
            $executed = true;
            return 'new_value';
        });

        $this->assertFalse($executed);
        $this->assertEquals('computed', $result);
    }

    public function test_expired_items_return_null()
    {
        $store = $this->cache->store('array');

        // Put with TTL of -1 (already expired)
        $store->put('expired', 'value', -1);

        $this->assertNull($store->get('expired'));
        $this->assertFalse($store->has('expired'));
    }
}
