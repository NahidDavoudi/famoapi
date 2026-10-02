<?php
declare(strict_types=1);

namespace Tests\Modules\Auth;

use App\Core\RateLimiter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tests\TestCase;

class AuthThrottleTest extends TestCase
{
    /** @var array<string,string|null> */
    private array $envBackup = [];

    protected function createRequest(
        string $method,
        string $uri,
        array $body = [],
        array $headers = [],
        array $cookies = [],
        array $queryParams = []
    ): ServerRequestInterface {
        $request = $this->requestFactory->createServerRequest($method, $uri, $_SERVER);

        if (!empty($body)) {
            $request = $request->withParsedBody($body);
        }

        if (!empty($headers)) {
            foreach ($headers as $key => $value) {
                $request = $request->withHeader($key, $value);
            }
        }

        if (!empty($cookies)) {
            $request = $request->withCookieParams($cookies);
        }

        if (!empty($queryParams)) {
            $request = $request->withQueryParams($queryParams);
        }

        return $request;
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'TRUSTED_PROXIES',
            'TRUSTED_PROXY_HEADER',
            'AUTH_LOGIN_MAX_ATTEMPTS',
            'AUTH_LOGIN_WINDOW_SECONDS',
            'AUTH_LOGIN_LOCKOUT_SECONDS',
            'AUTH_LOGIN_IP_MAX_ATTEMPTS',
            'AUTH_LOGIN_USER_SOFT_MAX',
            'AUTH_2FA_MAX_ATTEMPTS',
            'AUTH_2FA_CHALLENGE_TTL',
            'AUTH_SMS_PHONE_HOURLY_MAX',
            'AUTH_SMS_IP_HOURLY_MAX',
        ] as $key) {
            $this->envBackup[$key] = $_ENV[$key] ?? null;
        }
        $_ENV['TRUSTED_PROXIES'] = '';
        $_ENV['TRUSTED_PROXY_HEADER'] = '';

        $this->db->exec('DELETE FROM auth_throttle');
        $this->db->exec('DELETE FROM login_codes');
        $this->db->exec("DELETE FROM users WHERE username LIKE 'throttle_%'");

        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }

        $this->db->exec('DELETE FROM auth_throttle');
        $this->db->exec('DELETE FROM login_codes');
        $this->db->exec("DELETE FROM users WHERE username LIKE 'throttle_%'");

        parent::tearDown();
    }

    private function seedUser(string $username, string $password, string $role = 'student'): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, password_hash, role, full_name, created_at)
             VALUES (:username, :password_hash, :role, :full_name, NOW())'
        );
        $stmt->execute([
            'username'      => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => $role,
            'full_name'     => 'Throttle Test ' . $role,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function loginAttempt(string $username, string $password, string $ip): ResponseInterface
    {
        $_SERVER['REMOTE_ADDR'] = $ip;

        $request = $this->jsonRequest('POST', '/api/v1/auth/login', [
            'username' => $username,
            'password' => $password,
        ], ['Origin' => 'http://localhost']);

        return $this->handleRequest($request);
    }

    public function testUserIpLockoutReturns429(): void
    {
        $_ENV['AUTH_LOGIN_MAX_ATTEMPTS'] = '3';
        $_ENV['AUTH_LOGIN_WINDOW_SECONDS'] = '900';
        $_ENV['AUTH_LOGIN_LOCKOUT_SECONDS'] = '900';

        $username = 'throttle_lockout';
        $this->seedUser($username, 'correct-password');

        for ($i = 0; $i < 3; $i++) {
            $response = $this->loginAttempt($username, 'wrong-password', '198.51.100.1');
            $this->assertJsonResponse($response, 401, null, 'AUTH_ERROR');
        }

        $locked = $this->loginAttempt($username, 'wrong-password', '198.51.100.1');
        $this->assertJsonResponse($locked, 429, null, 'RATE_LIMITED');
        $this->assertGreaterThan(0, (int) $locked->getHeaderLine('Retry-After'));
    }

    public function testDifferentIpIsNotLocked(): void
    {
        $_ENV['AUTH_LOGIN_MAX_ATTEMPTS'] = '3';

        $username = 'throttle_other_ip';
        $this->seedUser($username, 'correct-password');

        for ($i = 0; $i < 3; $i++) {
            $this->loginAttempt($username, 'wrong-password', '198.51.100.2');
        }

        $response = $this->loginAttempt($username, 'wrong-password', '198.51.100.3');
        $this->assertJsonResponse($response, 401, null, 'AUTH_ERROR');
    }

    public function testSuccessfulLoginClearsUserIpCounter(): void
    {
        $_ENV['AUTH_LOGIN_MAX_ATTEMPTS'] = '3';

        $username = 'throttle_clear';
        $password = 'correct-password';
        $this->seedUser($username, $password);
        $ip = '198.51.100.4';

        $this->loginAttempt($username, 'wrong-password', $ip);
        $this->loginAttempt($username, 'wrong-password', $ip);

        $success = $this->loginAttempt($username, $password, $ip);
        $this->assertSuccessResponse($success, ['token', 'user']);

        $this->assertNull($this->fetchOne('auth_throttle', [
            'throttle_key' => RateLimiter::key('login:user_ip', $username . '|' . $ip),
        ]));
        $this->assertNull($this->fetchOne('auth_throttle', [
            'throttle_key' => RateLimiter::key('login:user', $username),
        ]));

        $this->loginAttempt($username, 'wrong-password', $ip);
        $afterReset = $this->loginAttempt($username, 'wrong-password', $ip);
        $this->assertJsonResponse($afterReset, 401, null, 'AUTH_ERROR');
    }

    public function testSmsPhoneCapReturns429(): void
    {
        $_ENV['AUTH_SMS_PHONE_HOURLY_MAX'] = '1';

        $username = 'throttle_sms_admin';
        $password = 'correct-password';
        $this->seedUser($username, $password, 'admin');

        RateLimiter::attempt(RateLimiter::key('sms:phone', $username), 1, 3600, 3600);

        $response = $this->loginAttempt($username, $password, '198.51.100.5');
        $this->assertJsonResponse($response, 429, null, 'RATE_LIMITED');
        $this->assertGreaterThan(0, (int) $response->getHeaderLine('Retry-After'));
    }

    public function testVerify2faAttemptCapReturns429(): void
    {
        $_ENV['AUTH_2FA_MAX_ATTEMPTS'] = '2';
        $_ENV['AUTH_2FA_CHALLENGE_TTL'] = '300';

        $userId = $this->seedUser('throttle_2fa_admin', 'correct-password', 'admin');

        for ($i = 0; $i < 2; $i++) {
            $request = $this->jsonRequest('POST', '/api/v1/auth/verify-2fa', [
                'user_id' => $userId,
                'code'    => '000000',
            ], ['Origin' => 'http://localhost']);
            $this->assertJsonResponse($this->handleRequest($request), 401, null, '2FA_ERROR');
        }

        $request = $this->jsonRequest('POST', '/api/v1/auth/verify-2fa', [
            'user_id' => $userId,
            'code'    => '000000',
        ], ['Origin' => 'http://localhost']);
        $locked = $this->handleRequest($request);
        $this->assertJsonResponse($locked, 429, null, 'RATE_LIMITED');
        $this->assertGreaterThan(0, (int) $locked->getHeaderLine('Retry-After'));
    }
}
