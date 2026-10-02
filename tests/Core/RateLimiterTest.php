<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\RateLimiter;
use Tests\TestCase;

class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->exec("DELETE FROM auth_throttle WHERE throttle_key LIKE 'test:%'");
    }

    public function testLocksAfterMaxAttemptsAndClears(): void
    {
        $key = RateLimiter::key('test:login', 'user-a|1.2.3.4');
        for ($i = 0; $i < 3; $i++) {
            $status = RateLimiter::attempt($key, 3, 900, 900);
        }
        $this->assertFalse($status['allowed']);
        $this->assertGreaterThan(0, $status['retry_after']);

        RateLimiter::clear($key);
        $this->assertTrue(RateLimiter::status($key, 3)['allowed']);
    }

    public function testDifferentKeysAreIndependent(): void
    {
        $a = RateLimiter::key('test:login', 'user-a|1.1.1.1');
        $b = RateLimiter::key('test:login', 'user-a|2.2.2.2');
        RateLimiter::attempt($a, 2, 900, 900);
        RateLimiter::attempt($a, 2, 900, 900);
        $this->assertFalse(RateLimiter::status($a, 2)['allowed']);
        $this->assertTrue(RateLimiter::status($b, 2)['allowed']);
    }
}
