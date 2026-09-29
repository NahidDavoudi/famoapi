<?php

namespace Tests\Core;

use App\Core\Cache;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'famo_cache_test_' . bin2hex(random_bytes(6));
        Cache::configure($this->dir, true);
    }

    protected function tearDown(): void
    {
        Cache::configure($this->dir, false);
        $this->removeDirectory($this->dir);
        parent::tearDown();
    }

    public function testRememberRunsCallbackOnceAndReturnsCachedValue(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return ['value' => 42];
        };

        $first = Cache::remember('group', 'key', 60, $callback);
        $second = Cache::remember('group', 'key', 60, $callback);

        self::assertSame(['value' => 42], $first);
        self::assertSame(['value' => 42], $second);
        self::assertSame(1, $calls);
    }

    public function testNullIsNotCached(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return null;
        };

        self::assertNull(Cache::remember('group', 'missing', 60, $callback));
        self::assertNull(Cache::remember('group', 'missing', 60, $callback));
        self::assertSame(2, $calls);
    }

    public function testExpiredEntryRunsCallbackAgain(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return 'fresh';
        };

        Cache::remember('group', 'ttl', 1, $callback);
        sleep(2);
        Cache::remember('group', 'ttl', 1, $callback);

        self::assertSame(2, $calls);
    }

    public function testDisabledCacheAlwaysRunsCallback(): void
    {
        Cache::configure($this->dir, false);

        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return 'value';
        };

        Cache::remember('group', 'key', 60, $callback);
        Cache::remember('group', 'key', 60, $callback);

        self::assertSame(2, $calls);
    }

    public function testForgetRemovesEntry(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return 'value';
        };

        Cache::remember('group', 'key', 60, $callback);
        Cache::forget('group', 'key');
        Cache::remember('group', 'key', 60, $callback);

        self::assertSame(2, $calls);
    }

    public function testFlushGroupRemovesAllEntriesInGroupOnly(): void
    {
        Cache::remember('group', 'a', 60, fn() => 'a');
        Cache::remember('group', 'b', 60, fn() => 'b');
        Cache::remember('other', 'c', 60, fn() => 'c');

        Cache::flushGroup('group');

        $calls = 0;
        Cache::remember('group', 'a', 60, function () use (&$calls) {
            $calls++;
            return 'a';
        });
        Cache::remember('other', 'c', 60, function () use (&$calls) {
            $calls++;
            return 'c';
        });

        self::assertSame(1, $calls);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*') ?: [] as $path) {
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
