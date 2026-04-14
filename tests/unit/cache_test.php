<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Cache;

final class CacheTest extends TestCase
{
    private string $testCachePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testCachePath = sys_get_temp_dir() . '/cms_test_cache_' . uniqid();
        Cache::setDriver('file');
    }

    protected function tearDown(): void
    {
        $this->clearTestCache();
        if (is_dir($this->testCachePath)) {
            rmdir($this->testCachePath);
        }
        parent::tearDown();
    }

    public function testSetAndGet(): void
    {
        Cache::set('test_key', 'test_value', 3600);
        $this->assertSame('test_value', Cache::get('test_key'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $result = Cache::get('nonexistent_key', 'default_value');
        $this->assertSame('default_value', $result);
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        Cache::set('has_key', 'value', 3600);
        $this->assertTrue(Cache::has('has_key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse(Cache::has('missing_key'));
    }

    public function testForgetRemovesKey(): void
    {
        Cache::set('forget_key', 'value', 3600);
        Cache::forget('forget_key');
        $this->assertNull(Cache::get('forget_key'));
    }

    public function testRememberReturnsCachedValue(): void
    {
        Cache::set('remember_key', 'cached_value', 3600);
        
        $callbackCalled = false;
        $result = Cache::remember('remember_key', 3600, function () use (&$callbackCalled) {
            $callbackCalled = true;
            return 'new_value';
        });

        $this->assertSame('cached_value', $result);
        $this->assertFalse($callbackCalled);
    }

    public function testRememberCallsCallbackForMissingKey(): void
    {
        $callbackCalled = false;
        $result = Cache::remember('new_remember_key', 3600, function () use (&$callbackCalled) {
            $callbackCalled = true;
            return 'computed_value';
        });

        $this->assertSame('computed_value', $result);
        $this->assertTrue($callbackCalled);
    }

    public function testIncrement(): void
    {
        Cache::set('counter', 10, 3600);
        $result = Cache::increment('counter');
        $this->assertSame(11, $result);
    }

    public function testDecrement(): void
    {
        Cache::set('counter', 10, 3600);
        $result = Cache::decrement('counter');
        $this->assertSame(9, $result);
    }

    public function testStoresArrays(): void
    {
        $data = ['name' => 'Test', 'values' => [1, 2, 3]];
        Cache::set('array_key', $data, 3600);
        $this->assertSame($data, Cache::get('array_key'));
    }

    public function testStoresObjects(): void
    {
        $object = new \stdClass();
        $object->name = 'Test Object';
        Cache::set('object_key', $object, 3600);
        
        $retrieved = Cache::get('object_key');
        $this->assertSame('Test Object', $retrieved->name);
    }

    private function clearTestCache(): void
    {
        $cachePath = sys_get_temp_dir() . '/cms_test_cache';
        if (is_dir($cachePath)) {
            foreach (glob($cachePath . '/*.cache') as $file) {
                @unlink($file);
            }
        }
    }
}
