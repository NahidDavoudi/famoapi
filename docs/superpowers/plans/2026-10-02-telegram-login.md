# Telegram Login & Account Linking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Telegram Login Widget verification and account linking (student register / student link / supporter link with 2FA) plus login throttling, returning the user to `https://t.me/<bot>?start=linked`.

**Architecture:** Four public endpoints under `/api/v1/auth/telegram/*` verify a Telegram-signed payload offline, issue a short-lived single-use ticket (separate signing key + audience from session JWTs), and create `telegram_links` rows only after credentials/2FA succeed. A DB-backed fixed-window `RateLimiter` throttles normal login, 2FA, and the Telegram endpoints. The website login page embeds the Telegram widget and drives the flow.

**Tech Stack:** PHP 8.2+, Slim 4, PDO/MariaDB, firebase/php-jwt, PHPUnit 9.5, OpenAPI 3.1, plain ES-module JS.

**Spec:** `docs/superpowers/specs/2026-10-02-telegram-login-design.md`

## Global Constraints

- API responses always use `{success,data,pagination,error}`.
- Never store `TELEGRAM_BOT_TOKEN` in the API; only `TELEGRAM_LOGIN_SECRET_KEY` (hex `SHA256(bot_token)`).
- Ticket signing key is **distinct** from `JWT_SECRET`; tickets carry `aud='tg_link'` and `purpose='tg_link'`.
- `/telegram/verify` must never return a token for any role.
- Never log tickets, widget payloads, hashes, passwords, or 2FA codes; the ticket never appears in a URL.
- Normal login success responses must stay byte-identical; only throttled requests gain `429 RATE_LIMITED` + `Retry-After`.
- Check order in link/verify-2fa: throttle → ticket → credentials/2FA → role gate → conflict errors → create link.
- `telegram_links` schema is unchanged; do not add migrations that alter it.
- Do not modify the bot host (`tel-bot.zip`); docs only.
- Do not commit unless the repo owner has explicitly authorized commits in this session.

## Test and run commands

- Full suite: `php vendor/bin/phpunit`
- Single file: `php vendor/bin/phpunit tests/Modules/Auth/TelegramAuthTest.php`
- Single test: `php vendor/bin/phpunit --filter testName tests/Modules/Auth/TelegramAuthTest.php`
- Migrations: `php migrate.php`
- Syntax check: `php -l <file>`

## File structure

New:
- `database/migrations/013_telegram_login_auth.sql` — nonces, throttle, 2FA challenges.
- `app/Core/ClientIp.php` — trusted-proxy-aware client IP.
- `app/Core/RateLimiter.php` — DB fixed-window limiter.
- `app/Core/RateLimitedException.php` — ApiException carrying `retry_after`.
- `app/Modules/Auth/TelegramTicket.php` — issue/validate/consume.
- `app/Modules/Auth/TelegramLoginVerifier.php` — widget signature + replay.
- `app/Modules/Auth/TelegramAuthService.php` — orchestration.
- `app/Modules/Auth/TelegramAuthController.php` — endpoints.
- `tests/Core/ClientIpTest.php`, `tests/Core/RateLimiterTest.php`, `tests/Modules/Auth/TelegramAuthTest.php`.

Modified:
- `app/Core/Auth.php` — add `encodeWithKey` / `decodeWithKey`.
- `app/Modules/Auth/AuthService.php` — extract helpers; `consume2faCode`; SMS caps.
- `app/Modules/Auth/AuthController.php` — throttle login/verify-2fa; 429 + Retry-After; SMS caps.
- `app/Modules/Linking/LinkService.php` — extract `linkVerified`.
- `routes/api.php` — four routes.
- `.env`, `.env.example` — new vars.
- `login/index.php`, `login/assets/js/api.js`, `login/assets/js/login.js`, `login/config.php`, `login/.env.example`.
- `openapi.yaml`, `docs/bot-host-api-contract.md`.

---

### Task 1: Schema and configuration foundation

**Files:**
- Create: `database/migrations/013_telegram_login_auth.sql`
- Modify: `.env.example`, `.env`
- Test: `tests/Core/MigratorTest.php` (existing smoke) + `php migrate.php`

**Interfaces:**
- Produces: tables `telegram_auth_nonces`, `auth_throttle`, `telegram_2fa_challenges`.

- [ ] **Step 1: Write the migration**

```sql
-- Migration 013: Telegram login handoff support.
-- Adds single-use nonces (widget payload replay + ticket jti), a fixed-window
-- throttle table, and server-bound 2FA challenges. Does not alter telegram_links.

CREATE TABLE IF NOT EXISTS telegram_auth_nonces (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    kind ENUM('payload','ticket') NOT NULL,
    nonce VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_kind_nonce (kind, nonce),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS auth_throttle (
    throttle_key VARCHAR(191) NOT NULL,
    attempts INT(11) NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    locked_until DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (throttle_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS telegram_2fa_challenges (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    challenge_nonce VARCHAR(64) NOT NULL,
    ticket_jti VARCHAR(64) NOT NULL,
    user_id INT(11) NOT NULL,
    attempts INT(11) NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_challenge_nonce (challenge_nonce),
    KEY idx_ticket_jti (ticket_jti),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
```

- [ ] **Step 2: Run the migration**

Run: `php migrate.php`
Expected: JSON with `"013_telegram_login_auth.sql"` in `executed`.

- [ ] **Step 3: Document env vars in `.env.example`**

Append (commented, with defaults and generation note):

```ini
# --- Telegram Login (widget) ---
# Derived from the bot token: php -r "echo hash('sha256', '<BOT_TOKEN>');"
TELEGRAM_LOGIN_SECRET_KEY=
TELEGRAM_LOGIN_MAX_AGE=300
TELEGRAM_AUTH_TICKET_TTL=600
TELEGRAM_BOT_URL=
# --- Trusted proxy / client IP ---
TRUSTED_PROXIES=
TRUSTED_PROXY_HEADER=
# --- Auth throttling ---
AUTH_LOGIN_MAX_ATTEMPTS=5
AUTH_LOGIN_WINDOW_SECONDS=900
AUTH_LOGIN_LOCKOUT_SECONDS=900
AUTH_LOGIN_IP_MAX_ATTEMPTS=30
AUTH_LOGIN_USER_SOFT_MAX=20
AUTH_2FA_MAX_ATTEMPTS=5
AUTH_2FA_CHALLENGE_TTL=300
AUTH_SMS_PHONE_HOURLY_MAX=5
AUTH_SMS_IP_HOURLY_MAX=20
AUTH_TELEGRAM_VERIFY_PER_IP=30
AUTH_TELEGRAM_VERIFY_PER_TG=10
AUTH_TELEGRAM_REGISTER_PER_IP=10
AUTH_TELEGRAM_REGISTER_PER_TG=10
```

- [ ] **Step 4: Add the same keys (with working local values) to `.env`** so manual testing works; leave `TELEGRAM_LOGIN_SECRET_KEY` and `TELEGRAM_BOT_URL` empty until the bot token is available.

- [ ] **Step 5: Checkpoint** — review `git diff`; commit only if authorized.

---

### Task 2: ClientIp with trusted-proxy support

**Files:**
- Create: `app/Core/ClientIp.php`
- Test: `tests/Core/ClientIpTest.php`

**Interfaces:**
- Produces: `ClientIp::fromRequest(ServerRequestInterface $request): ?string` (null = undeterminable; callers fail open).

- [ ] **Step 1: Write the failing tests**

> The Slim `ServerRequestFactory` seeds server params from `$_SERVER`, so set `$_SERVER['REMOTE_ADDR']` before creating each request.

```php
<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\ClientIp;
use Tests\TestCase;

class ClientIpTest extends TestCase
{
    public function testUsesRemoteAddrByDefault(): void
{
    $_ENV['TRUSTED_PROXIES'] = '';
    $_ENV['TRUSTED_PROXY_HEADER'] = '';
    $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
    $request = $this->createRequest('GET', '/x')->withAddedHeader('X-Forwarded-For', '9.9.9.9');
    $this->assertSame('198.51.100.9', ClientIp::fromRequest($request));
}

public function testTrustedProxyHeaderUsedOnlyFromTrustedProxy(): void
{
    $_ENV['TRUSTED_PROXIES'] = '10.0.0.0/8';
    $_ENV['TRUSTED_PROXY_HEADER'] = 'X-Forwarded-For';

    $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
    $trusted = $this->createRequest('GET', '/x')->withAddedHeader('X-Forwarded-For', '203.0.113.7, 10.0.0.5');
    $this->assertSame('203.0.113.7', ClientIp::fromRequest($trusted));

    $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
    $untrusted = $this->createRequest('GET', '/x')->withAddedHeader('X-Forwarded-For', '203.0.113.7');
    $this->assertSame('198.51.100.9', ClientIp::fromRequest($untrusted));
}

public function testInvalidAddressFailsOpen(): void
{
    $_SERVER['REMOTE_ADDR'] = 'not-an-ip';
    $_ENV['TRUSTED_PROXIES'] = '';
    $_ENV['TRUSTED_PROXY_HEADER'] = '';
    $this->assertNull(ClientIp::fromRequest($this->createRequest('GET', '/x')));
}
```

- [ ] **Step 2: Run and confirm failure**

Run: `php vendor/bin/phpunit tests/Core/ClientIpTest.php`
Expected: FAIL (class not found).

- [ ] **Step 3: Implement `ClientIp`**

```php
<?php
declare(strict_types=1);

namespace App\Core;

use Psr\Http\Message\ServerRequestInterface;

final class ClientIp
{
    public static function fromRequest(ServerRequestInterface $request): ?string
    {
        $remote = trim((string) ($request->getServerParams()['REMOTE_ADDR'] ?? ''));
        $header = trim((string) ($_ENV['TRUSTED_PROXY_HEADER'] ?? ''));
        $trusted = self::trustedProxies();

        $client = $remote;
        if ($header !== '' && $remote !== '' && self::isTrusted($remote, $trusted)) {
            $first = trim((string) (explode(',', $request->getHeaderLine($header))[0] ?? ''));
            if ($first !== '') {
                $client = $first;
            }
        }

        if ($client === '' || filter_var($client, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $client;
    }

    /** @return list<string> */
    private static function trustedProxies(): array
    {
        $raw = (string) ($_ENV['TRUSTED_PROXIES'] ?? '');
        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn ($v) => $v !== ''));
    }

    private static function isTrusted(string $ip, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            if (self::inCidr($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    private static function inCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $bitsRaw] = explode('/', $cidr, 2);
        $bits = (int) $bitsRaw;
        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton($subnet);
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin) || $bits < 0 || $bits > strlen($ipBin) * 8) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        $rem = $bits % 8;
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
            return false;
        }
        if ($rem === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rem)) & 0xFF;
        return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
    }
}
```

- [ ] **Step 4: Run and confirm pass**

Run: `php vendor/bin/phpunit tests/Core/ClientIpTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Checkpoint** — commit only if authorized.

---

### Task 3: DB-backed RateLimiter and RateLimitedException

**Files:**
- Create: `app/Core/RateLimiter.php`, `app/Core/RateLimitedException.php`
- Test: `tests/Core/RateLimiterTest.php`

**Interfaces:**
- Produces:
  - `RateLimiter::key(string $scope, string $value): string`
  - `RateLimiter::status(string $key, ?int $max = null): array{allowed:bool,retry_after:int,attempts:int}`
  - `RateLimiter::attempt(string $key, int $max, int $windowSeconds, ?int $lockoutSeconds = null): array{allowed:bool,retry_after:int,attempts:int}`
  - `RateLimiter::clear(string $key): void`
  - `RateLimiter::prune(): int`
  - `RateLimitedException extends ApiException` with `getRetryAfter(): int`.

- [ ] **Step 1: Write the failing tests**

```php
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
```

- [ ] **Step 2: Run and confirm failure**

Run: `php vendor/bin/phpunit tests/Core/RateLimiterTest.php`
Expected: FAIL (class not found / table rows absent).

- [ ] **Step 3: Implement**

`app/Core/RateLimitedException.php`:

```php
<?php
declare(strict_types=1);

namespace App\Core;

final class RateLimitedException extends ApiException
{
    public function __construct(private int $retryAfter, string $message = 'تعداد تلاش‌ها بیش از حد مجاز است. لطفاً بعداً تلاش کنید.')
    {
        parent::__construct($message, 429, 'RATE_LIMITED');
    }

    public function getRetryAfter(): int
    {
        return max(1, $this->retryAfter);
    }
}
```

`app/Core/RateLimiter.php`:

```php
<?php
declare(strict_types=1);

namespace App\Core;

final class RateLimiter
{
    public static function key(string $scope, string $value): string
    {
        return substr($scope . ':' . hash('sha256', $value), 0, 191);
    }

    public static function status(string $key, ?int $max = null): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT attempts, locked_until FROM auth_throttle WHERE throttle_key = :k LIMIT 1'
        );
        $stmt->execute(['k' => $key]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['allowed' => true, 'retry_after' => 0, 'attempts' => 0];
        }

        $lockedUntil = $row['locked_until'] !== null ? (int) strtotime((string) $row['locked_until'] . ' UTC') : 0;
        $retryAfter = max(0, $lockedUntil - time());
        $attempts = (int) $row['attempts'];

        return [
            'allowed' => $retryAfter === 0 && ($max === null || $attempts < $max),
            'retry_after' => $retryAfter,
            'attempts' => $attempts,
        ];
    }

    public static function attempt(string $key, int $max, int $windowSeconds, ?int $lockoutSeconds = null): array
    {
        $window = max(1, $windowSeconds);
        $lockout = max(1, $lockoutSeconds ?? $windowSeconds);
        $max = max(1, $max);

        $sql = 'INSERT INTO auth_throttle (throttle_key, attempts, window_started_at, locked_until)
                VALUES (:k, 1, UTC_TIMESTAMP(), NULL)
                ON DUPLICATE KEY UPDATE
                    attempts = IF(window_started_at < (UTC_TIMESTAMP() - INTERVAL ' . $window . ' SECOND), 1, attempts + 1),
                    window_started_at = IF(window_started_at < (UTC_TIMESTAMP() - INTERVAL ' . $window . ' SECOND), UTC_TIMESTAMP(), window_started_at),
                    locked_until = IF(attempts >= ' . $max . ', UTC_TIMESTAMP() + INTERVAL ' . $lockout . ' SECOND, locked_until)';
        Database::getConnection()->prepare($sql)->execute(['k' => $key]);

        return self::status($key, $max);
    }

    public static function clear(string $key): void
    {
        $stmt = Database::getConnection()->prepare('DELETE FROM auth_throttle WHERE throttle_key = :k');
        $stmt->execute(['k' => $key]);
    }

    public static function prune(): int
    {
        $stmt = Database::getConnection()->prepare(
            'DELETE FROM auth_throttle
             WHERE (locked_until IS NULL OR locked_until < UTC_TIMESTAMP())
               AND window_started_at < (UTC_TIMESTAMP() - INTERVAL 1 DAY)'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
```

- [ ] **Step 4: Run and confirm pass**

Run: `php vendor/bin/phpunit tests/Core/RateLimiterTest.php`
Expected: PASS.

- [ ] **Step 5: Checkpoint** — commit only if authorized.

---

### Task 4: Auth key helpers, ticket, and widget verifier

**Files:**
- Modify: `app/Core/Auth.php`
- Create: `app/Modules/Auth/TelegramTicket.php`, `app/Modules/Auth/TelegramLoginVerifier.php`
- Test: `tests/Modules/Auth/TelegramAuthTest.php` (create with the unit cases below)

**Interfaces:**
- Produces:
  - `Auth::encodeWithKey(array $claims, string $key, int $ttlSeconds): string`
  - `Auth::decodeWithKey(string $token, string $key): object`
  - `TelegramTicket::issue(int $telegramUserId, ?string $telegramUsername): string`
  - `TelegramTicket::validate(string $ticket): object`
  - `TelegramTicket::consume(string $ticket): object`
  - `TelegramTicket::jti(object $claims): string`
  - `TelegramLoginVerifier::verify(array $payload): array{id:int,username:?string,first_name:?string,last_name:?string,photo_url:?string}`
  - `TelegramLoginVerifier::canonicalHash(array $payload): string`

- [ ] **Step 1: Add helpers to `Auth`**

```php
public static function encodeWithKey(array $claims, string $key, int $ttlSeconds): string
{
    $payload = $claims + ['iat' => time(), 'exp' => time() + $ttlSeconds];
    return JWT::encode($payload, $key, self::$algorithm);
}

public static function decodeWithKey(string $token, string $key): object
{
    return JWT::decode($token, new Key($key, self::$algorithm));
}
```

- [ ] **Step 2: Write the failing tests** (widget hash + ticket separation + single-use)

```php
<?php
declare(strict_types=1);

namespace Tests\Modules\Auth;

use App\Core\Auth;
use App\Modules\Auth\TelegramLoginVerifier;
use App\Modules\Auth\TelegramTicket;
use Tests\TestCase;

class TelegramAuthTest extends TestCase
{
    private string $secretHex;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secretHex = hash('sha256', 'test-bot-token');
        $_ENV['TELEGRAM_LOGIN_SECRET_KEY'] = $this->secretHex;
        $_ENV['TELEGRAM_LOGIN_MAX_AGE'] = '300';
        $_ENV['TELEGRAM_AUTH_TICKET_TTL'] = '600';
        $this->db->exec("DELETE FROM telegram_auth_nonces");
        $this->db->exec("DELETE FROM telegram_2fa_challenges");
    }

    private function widgetPayload(int $id = 880000011, ?int $authDate = null): array
    {
        $payload = [
            'id' => $id,
            'first_name' => 'Test',
            'username' => 'testuser',
            'auth_date' => $authDate ?? time(),
        ];
        $pairs = $payload;
        ksort($pairs, SORT_STRING);
        $lines = [];
        foreach ($pairs as $k => $v) {
            $lines[] = $k . '=' . $v;
        }
        $payload['hash'] = hash_hmac('sha256', implode("\n", $lines), hex2bin($this->secretHex));
        return $payload;
    }

    public function testVerifierAcceptsValidPayload(): void
    {
        $tg = TelegramLoginVerifier::verify($this->widgetPayload());
        $this->assertSame(880000011, $tg['id']);
    }

    public function testVerifierRejectsBadHash(): void
    {
        $payload = $this->widgetPayload();
        $payload['hash'] = str_repeat('a', 64);
        $this->expectException(\App\Core\ApiException::class);
        TelegramLoginVerifier::verify($payload);
    }

    public function testVerifierRejectsStaleAuthDate(): void
    {
        $payload = $this->widgetPayload(880000012, time() - 1000);
        $this->expectException(\App\Core\ApiException::class);
        TelegramLoginVerifier::verify($payload);
    }

    public function testVerifierRejectsPayloadReplay(): void
    {
        $payload = $this->widgetPayload(880000013);
        TelegramLoginVerifier::verify($payload);
        $this->expectException(\App\Core\ApiException::class);
        TelegramLoginVerifier::verify($payload);
    }

    public function testTicketIsSingleUse(): void
    {
        $ticket = TelegramTicket::issue(880000014, 'u');
        TelegramTicket::consume($ticket);
        $this->expectException(\App\Core\ApiException::class);
        TelegramTicket::consume($ticket);
    }

    public function testTicketIsNotAcceptedAsSessionJwt(): void
    {
        $ticket = TelegramTicket::issue(880000015, 'u');
        $this->expectException(\Throwable::class);
        Auth::decode($ticket);
    }

    public function testSessionJwtIsNotAcceptedAsTicket(): void
    {
        $jwt = Auth::encode(['id' => 1, 'role' => 'student', 'student_id' => 1]);
        $this->expectException(\App\Core\ApiException::class);
        TelegramTicket::validate($jwt);
    }
}
```

- [ ] **Step 3: Run and confirm failure**

Run: `php vendor/bin/phpunit tests/Modules/Auth/TelegramAuthTest.php`
Expected: FAIL (classes not found).

- [ ] **Step 4: Implement `TelegramTicket`** (signature: `issue/validate/consume/jti`; key = `hash_hmac('sha256','famo.telegram.link.ticket.v1',$jwtSecret,true)`; claims `aud/purpose/jti/tg_user_id/tg_username`; `consume` = validate + `UPDATE telegram_auth_nonces SET consumed_at=NOW() WHERE kind='ticket' AND nonce=:jti AND consumed_at IS NULL AND expires_at>NOW()` requiring `rowCount()===1`).

- [ ] **Step 5: Implement `TelegramLoginVerifier`** (data-check-string over all scalar fields except `hash`, `ksort` SORT_STRING, `\n` join; compare `hash_hmac('sha256',$string,hex2bin($secret))`; freshness; replay insert catching SQLSTATE `23000` only and rethrowing otherwise as `TELEGRAM_REPLAY`).

- [ ] **Step 6: Run and confirm pass**

Run: `php vendor/bin/phpunit tests/Modules/Auth/TelegramAuthTest.php`
Expected: PASS (7 tests).

- [ ] **Step 7: Checkpoint** — commit only if authorized.

---

### Task 5: Extract AuthService helpers (no behavior change)

**Files:**
- Modify: `app/Modules/Auth/AuthService.php`
- Test: `tests/Modules/Auth/AuthTest.php` (existing)

**Interfaces:**
- Produces:
  - `createStudentAccount(array $data): array{userId:int,studentId:int}` — begins a transaction only if none is active; performs the duplicate-phone check, user+student insert, and `users.linked_id` update.
  - `sessionForUser(int $userId): array` — reloads the user and returns `['token'=>..., 'user'=>...]` for any role (no 2FA logic).
  - `consume2faCode(int $userId, string $code): array` — validates and consumes a `login_codes` row, returns the user row; throws `2FA_ERROR` on failure.
- `register()` and `verify2fa()` are rewritten in terms of these; observable output unchanged.

- [ ] **Step 1: Add a regression test asserting register output shape** (extend `AuthTest`, or rely on existing `testRegisterSuccess`). No new test needed; existing tests are the guard.

- [ ] **Step 2: Refactor `register()`** to call `createStudentAccount()` then `sessionForUser($userId)`. Keep the same returned keys (`token`, `user` with `id/username/role/student_id`) and same exceptions.

- [ ] **Step 3: Refactor `verify2fa()`** to call `consume2faCode()` then `sessionForUser($userId)` (which includes `instructor_id` handling for teachers). Keep behavior identical.

- [ ] **Step 4: Run the existing auth tests**

Run: `php vendor/bin/phpunit tests/Modules/Auth/AuthTest.php tests/Modules/Auth/TeacherAuthTest.php`
Expected: PASS (no regressions).

- [ ] **Step 5: Checkpoint** — commit only if authorized.

---

### Task 6: Extract LinkService::linkVerified

**Files:**
- Modify: `app/Modules/Linking/LinkService.php`
- Test: `tests/Modules/Bot/BotGatewayTest.php`, `tests/Modules/Threads/ThreadTest.php` (existing)

**Interfaces:**
- Produces: `LinkService::linkVerified(string $role, int $accountId, int $telegramUserId, int $chatId): array` returning the same payload as `link()`.
- `link()` keeps its `contact_verified` requirement and delegates validation of the account/link uniqueness to `linkVerified`.

- [ ] **Step 1: Move the body of `link()` after input validation into `linkVerified`.** `link()` validates inputs + `contact_verified`, then `return $this->linkVerified($role, $accountId, $telegramUserId, $chatId);`.

- [ ] **Step 2: Run the bot/link tests**

Run: `php vendor/bin/phpunit tests/Modules/Bot/BotGatewayTest.php`
Expected: PASS.

- [ ] **Step 3: Checkpoint** — commit only if authorized.

---

### Task 7: TelegramAuthService + Controller + `verify` route

**Files:**
- Create: `app/Modules/Auth/TelegramAuthService.php`, `app/Modules/Auth/TelegramAuthController.php`
- Modify: `routes/api.php`
- Test: `tests/Modules/Auth/TelegramAuthTest.php`

**Interfaces:**
- Produces:
  - `TelegramAuthService::verify(array $payload): array`
  - `TelegramAuthService::{register,beginLink,completeLink2fa}(...)` (implemented in Tasks 8–10)
  - `TelegramAuthController::{verify,register,link,verify2fa}(Request,Response): Response`
  - Routes: `POST /api/v1/auth/telegram/verify|register|link|verify-2fa`.

- [ ] **Step 1: Write failing integration tests for `verify`**

```php
public function testVerifyReturnsTicketWhenNotLinked(): void
{
    $payload = $this->widgetPayload(880000021);
    $response = $this->handleRequest($this->jsonRequest('POST', '/api/v1/auth/telegram/verify', $payload, ['Origin' => 'http://localhost']));
    $data = $this->assertSuccessResponse($response, ['linked', 'ticket', 'telegram']);
    $this->assertFalse($data['data']['linked']);
    $this->assertArrayNotHasKey('token', $data['data']);
}

public function testVerifyReturnsLinkedWithoutToken(): void
{
    $tgId = 880000022;
    $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id = $tgId");
    $studentId = $this->seedStudent(['phone' => '09990000022']);
    \App\Modules\Bot\TelegramLink::create($tgId, $tgId, 'student', $studentId);

    $response = $this->handleRequest($this->jsonRequest('POST', '/api/v1/auth/telegram/verify', $this->widgetPayload($tgId), ['Origin' => 'http://localhost']));
    $data = $this->assertSuccessResponse($response, ['linked', 'bot_redirect_url']);
    $this->assertTrue($data['data']['linked']);
    $this->assertArrayNotHasKey('token', $data['data']);
}
```

- [ ] **Step 2: Run and confirm 404/failure**

Run: `php vendor/bin/phpunit --filter Verify tests/Modules/Auth/TelegramAuthTest.php`
Expected: FAIL (route missing).

- [ ] **Step 3: Implement `TelegramAuthService::verify`**

```php
public function verify(array $payload): array
{
    $tg = TelegramLoginVerifier::verify($payload);

    foreach (BotActor::ROLES as $role) {
        if (TelegramLink::findByTelegramUserAndRole($tg['id'], $role)) {
            return ['linked' => true, 'bot_redirect_url' => $this->botRedirect()];
        }
    }

    return [
        'linked' => false,
        'ticket' => TelegramTicket::issue($tg['id'], $tg['username']),
        'telegram' => $tg,
    ];
}

private function botRedirect(): ?string
{
    $base = trim((string) ($_ENV['TELEGRAM_BOT_URL'] ?? ''));
    return $base === '' ? null : rtrim($base, '/') . '?start=linked';
}
```

- [ ] **Step 4: Implement the controller and routes.** Controller uses `ResponseHelper::json($response, $result)`; service throws `ApiException` (global handler formats). Register:

```php
$app->post('/api/v1/auth/telegram/verify', [$telegramAuthController, 'verify']);
$app->post('/api/v1/auth/telegram/register', [$telegramAuthController, 'register']);
$app->post('/api/v1/auth/telegram/link', [$telegramAuthController, 'link']);
$app->post('/api/v1/auth/telegram/verify-2fa', [$telegramAuthController, 'verify2fa']);
```

Add throttle checks for verify (`AUTH_TELEGRAM_VERIFY_PER_IP`, `..._PER_TG`) using `ClientIp` + `RateLimiter`, returning `RateLimitedException`.

- [ ] **Step 5: Run and confirm pass**

Run: `php vendor/bin/phpunit --filter Verify tests/Modules/Auth/TelegramAuthTest.php`
Expected: PASS.

- [ ] **Step 6: Checkpoint** — commit only if authorized.

---

### Task 8: `register` endpoint

**Files:**
- Modify: `app/Modules/Auth/TelegramAuthService.php`, `app/Modules/Auth/TelegramAuthController.php`
- Test: `tests/Modules/Auth/TelegramAuthTest.php`

**Interfaces:**
- Consumes: `AuthService::createStudentAccount`, `LinkService::linkVerified`, `TelegramTicket`.
- Produces: `TelegramAuthService::register(array $data, string $ticket): array`.

- [ ] **Step 1: Write failing tests**

```php
public function testRegisterCreatesStudentAndLink(): void
{
    $tgId = 880000031;
    $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id = $tgId");
    $this->db->exec("DELETE FROM users WHERE username = '09990000031'");
    $this->db->exec("DELETE FROM students WHERE phone = '09990000031'");

    $verify = $this->handleRequest($this->jsonRequest('POST', '/api/v1/auth/telegram/verify', $this->widgetPayload($tgId), ['Origin' => 'http://localhost']));
    $ticket = json_decode((string) $verify->getBody(), true)['data']['ticket'];

    $response = $this->handleRequest($this->jsonRequest('POST', '/api/v1/auth/telegram/register', [
        'ticket' => $ticket,
        'phone' => '09990000031',
        'password' => 'password123',
        'nationalId' => '1234500031',
        'grade' => 10,
        'field' => 'ریاضی',
        'name' => 'TG Student',
    ], ['Origin' => 'http://localhost']));

    $data = $this->assertCreatedResponse($response, ['token', 'user']);
    $student = $this->fetchOne('students', ['phone' => '09990000031']);
    $link = $this->fetchOne('telegram_links', ['telegram_user_id' => $tgId, 'role' => 'student']);
    $this->assertNotNull($student);
    $this->assertNotNull($link);
    $this->assertEquals($student['id'], $link['account_id']);
}

public function testRegisterRejectsReusedTicket(): void
{
    // issue a ticket, consume it via a first successful register, then reuse -> TELEGRAM_TICKET_INVALID
}
```

- [ ] **Step 2: Run and confirm failure**

Run: `php vendor/bin/phpunit --filter Register tests/Modules/Auth/TelegramAuthTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement `register`** inside a single transaction:

```php
public function register(array $data, string $ticket): array
{
    TelegramTicket::validate($ticket);            // signature/aud/purpose/ttl
    $claims = TelegramTicket::validate($ticket);

    $db = Database::getConnection();
    $db->beginTransaction();
    try {
        $account = $this->auth->createStudentAccount($data);           // no nested commit
        TelegramTicket::consume($ticket);                              // rowCount enforced
        $this->links->linkVerified('student', $account['studentId'], (int) $claims->tg_user_id, (int) $claims->tg_user_id);
        $db->commit();
    } catch (\Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    return $this->auth->sessionForUser($account['userId']) + ['bot_redirect_url' => $this->botRedirect()];
}
```

Validation of `phone/password/nationalId/grade/field/name` uses the exact rules from `AuthController::register` (reuse `App\Core\Validator` in the controller before calling the service).

- [ ] **Step 4: Run and confirm pass**

Run: `php vendor/bin/phpunit --filter Register tests/Modules/Auth/TelegramAuthTest.php`
Expected: PASS.

- [ ] **Step 5: Checkpoint** — commit only if authorized.

---

### Task 9: `link` endpoint (student direct, supporter 2FA challenge)

**Files:**
- Modify: `app/Modules/Auth/TelegramAuthService.php`, `app/Modules/Auth/TelegramAuthController.php`
- Test: `tests/Modules/Auth/TelegramAuthTest.php`

**Interfaces:**
- Produces: `TelegramAuthService::beginLink(string $ticket, string $username, string $password): array`.

- [ ] **Step 1: Write failing tests** covering: student link success; bad credentials → `AUTH_ERROR` with no link state; unsupported role → `TELEGRAM_LINK_ROLE_UNSUPPORTED`; supporter returns `202 {requires_2fa,challenge_id,phone_mask}` and creates a `telegram_2fa_challenges` row bound to the ticket jti; conflict errors are not returned before authentication.

- [ ] **Step 2: Run and confirm failure**

Run: `php vendor/bin/phpunit --filter Link tests/Modules/Auth/TelegramAuthTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement `beginLink`** using the mandated check order:

```php
public function beginLink(string $ticket, string $username, string $password): array
{
    $claims = TelegramTicket::validate($ticket);

    $user = User::findByUsername($username);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        throw new ApiException('نام کاربری یا رمز عبور اشتباه است', 401, 'AUTH_ERROR');
    }
    if (isset($user['is_active']) && (int) $user['is_active'] === 0) {
        throw new ApiException('حساب کاربری غیرفعال است', 403, 'FORBIDDEN');
    }

    $role = (string) $user['role'];
    if (!in_array($role, ['student', 'supporter'], true)) {
        throw new ApiException('اتصال تلگرام برای این نقش پشتیبانی نمی‌شود', 403, 'TELEGRAM_LINK_ROLE_UNSUPPORTED');
    }

    if ($role === 'supporter') {
        $challenge = $this->issueSupporterChallenge($claims, (int) $user['id']); // sends SMS with caps
        return ['requires_2fa' => true, 'challenge_id' => $challenge['nonce'], 'phone_mask' => $challenge['mask']];
    }

    $accountId = (int) ($user['linked_id'] ?? 0);
    $result = $this->links->linkVerified('student', $accountId, (int) $claims->tg_user_id, (int) $claims->tg_user_id);
    TelegramTicket::consume($ticket);

    return $this->auth->sessionForUser((int) $user['id']) + [
        'link' => $result,
        'bot_redirect_url' => $this->botRedirect(),
    ];
}
```

`issueSupporterChallenge` (new private method): enforces `sms:phone`/`sms:ip` caps, creates a `login_codes` row + `telegram_2fa_challenges` row (`challenge_nonce`, `ticket_jti`, `user_id`, `expires_at`), sends the SMS, returns nonce + `phone_mask`. **Do not return or accept `user_id`.**

- [ ] **Step 4: Run and confirm pass**

Run: `php vendor/bin/phpunit --filter Link tests/Modules/Auth/TelegramAuthTest.php`
Expected: PASS.

- [ ] **Step 5: Checkpoint** — commit only if authorized.

---

### Task 10: `verify-2fa` endpoint with server-bound challenge

**Files:**
- Modify: `app/Modules/Auth/TelegramAuthService.php`, `app/Modules/Auth/TelegramAuthController.php`
- Test: `tests/Modules/Auth/TelegramAuthTest.php`

**Interfaces:**
- Produces: `TelegramAuthService::completeLink2fa(string $ticket, string $challengeId, string $code, ?int $clientUserId): array`.

- [ ] **Step 1: Write failing tests** covering: success links the supporter and returns a token; mismatched `user_id` rejected; attempt cap; expired challenge; reused challenge; conflict errors only after 2FA.

- [ ] **Step 2: Run and confirm failure**

Run: `php vendor/bin/phpunit --filter Verify2fa tests/Modules/Auth/TelegramAuthTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement `completeLink2fa`**:

```php
public function completeLink2fa(string $ticket, string $challengeId, string $code, ?int $clientUserId): array
{
    $claims = TelegramTicket::validate($ticket);

    $challenge = $this->loadChallenge($challengeId);                 // null/expired/consumed -> 2FA_ERROR
    if ($challenge['ticket_jti'] !== TelegramTicket::jti($claims)) {
        throw new ApiException('کد تأیید نامعتبر یا منقضی شده است', 401, '2FA_ERROR');
    }
    if ($clientUserId !== null && $clientUserId !== (int) $challenge['user_id']) {
        throw new ApiException('کد تأیید نامعتبر یا منقضی شده است', 401, '2FA_ERROR');
    }

    $max = max(1, (int) ($_ENV['AUTH_2FA_MAX_ATTEMPTS'] ?? 5));
    $key = RateLimiter::key('2fa:chal', $challengeId);
    if (RateLimiter::attempt($key, $max, (int) ($_ENV['AUTH_2FA_CHALLENGE_TTL'] ?? 300), null)['allowed'] === false) {
        throw new RateLimitedException((int) ($_ENV['AUTH_2FA_CHALLENGE_TTL'] ?? 300));
    }

    $user = $this->auth->consume2faCode((int) $challenge['user_id'], $code); // throws 2FA_ERROR
    $role = (string) $user['role'];
    if (!in_array($role, ['student', 'supporter'], true)) {
        throw new ApiException('اتصال تلگرام برای این نقش پشتیبانی نمی‌شود', 403, 'TELEGRAM_LINK_ROLE_UNSUPPORTED');
    }

    $accountId = (int) ($user['linked_id'] ?? 0);
    $this->links->linkVerified($role, $accountId, (int) $claims->tg_user_id, (int) $claims->tg_user_id);
    TelegramTicket::consume($ticket);
    $this->consumeChallenge($challengeId);

    return $this->auth->sessionForUser((int) $user['id']) + ['bot_redirect_url' => $this->botRedirect()];
}
```

- [ ] **Step 4: Make `TelegramAuthController` catch `RateLimitedException`** and emit `429` with `Retry-After: getRetryAfter()`.

- [ ] **Step 5: Run and confirm pass**

Run: `php vendor/bin/phpunit --filter Verify2fa tests/Modules/Auth/TelegramAuthTest.php`
Expected: PASS.

- [ ] **Step 6: Checkpoint** — commit only if authorized.

---

### Task 11: Throttle rollout to normal login/2FA + SMS caps

**Files:**
- Modify: `app/Modules/Auth/AuthController.php`, `app/Modules/Auth/AuthService.php`, `app/Core/ExceptionHandler.php`
- Test: `tests/Modules/Auth/AuthTest.php` (existing) + new `tests/Modules/Auth/AuthThrottleTest.php`

**Interfaces:**
- Consumes: `RateLimiter`, `ClientIp`, `RateLimitedException`.
- Produces: `429 + Retry-After` on throttled login/2FA; success responses unchanged.

- [ ] **Step 1: Write failing throttle tests**: `(username+IP)` lockout returns 429; a different IP is not locked; a successful login clears the `user_ip` counter; SMS send cap returns 429.

- [ ] **Step 2: Run and confirm failure**

Run: `php vendor/bin/phpunit tests/Modules/Auth/AuthThrottleTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement in `AuthController::login`** (and `verify2fa`): before calling the service, `status()`-check the keys; on `ApiException` from the service, `attempt()` each key; on success, `clear()` the `(username+IP)` and per-username keys. Catch `RateLimitedException` (and any service-thrown `RateLimitedException` for SMS caps) to set `Retry-After`. **Do not change the success JSON.**

- [ ] **Step 4: Add SMS caps in `AuthService::login`** where the 2FA code is sent (and in the Telegram supporter challenge path): check/hit `sms:phone` and `sms:ip`; over-limit throws `RateLimitedException`.

- [ ] **Step 5: Extend `ExceptionHandler`** to set `Retry-After` when the exception is a `RateLimitedException`.

- [ ] **Step 6: Run the full auth suite**

Run: `php vendor/bin/phpunit tests/Modules/Auth`
Expected: PASS (existing + new).

- [ ] **Step 7: Checkpoint** — commit only if authorized.

---

### Task 12: Website login page integration

**Files:**
- Modify: `login/index.php`, `login/assets/js/api.js`, `login/assets/js/login.js`, `login/config.php`, `login/.env.example`

**Interfaces:**
- Consumes: API endpoints from Tasks 7–10.
- Produces: widget + chooser UX; redirect to `bot_redirect_url`.

- [ ] **Step 1: Expose config** in `login/config.php`: add `TELEGRAM_BOT_USERNAME` to the `APP_CONFIG` JSON (`telegramBotUsername`).

- [ ] **Step 2: Add the widget** in `login/index.php` (above the tabs):

```html
<div id="telegramLogin" class="mb-6 flex justify-center">
  <script async src="https://telegram.org/js/telegram-widget.js"></script>
  <script>
    window.onTelegramAuth = function (user) { window.dispatchEvent(new CustomEvent('famo:telegram-auth', { detail: user })); };
  </script>
  <script async
    src="https://telegram.org/js/telegram-widget.js?22"
    data-telegram-login="<?= htmlspecialchars(famo_env('TELEGRAM_BOT_USERNAME'), ENT_QUOTES, 'UTF-8') ?>"
    data-size="large"
    data-onauth="onTelegramAuth(user)"
    data-request-access="write"></script>
</div>
<div id="telegramHint" class="hidden form-message error" role="alert"></div>
```

- [ ] **Step 3: Implement the flow in `login.js`**: listen for `famo:telegram-auth`; call `API.telegramVerify(detail)`; if `linked` → show message + button calling `window.location.assign(bot_redirect_url)`; else store `ticket`, show the chooser (`login existing` / `register`), and on form submit attach the ticket and call the matching API method. On `requires_2fa` show the 2FA form and submit to `API.telegramVerify2fa(ticket, challenge_id, code)`. On `RATE_LIMITED` show a Persian message. Add a 5s fallback that reveals `#telegramHint` ("اگر ویجت باز نشد، اتصال به تلگرام/فیلترشکن را بررسی و دوباره تلاش کنید.") when the widget iframe did not appear.

- [ ] **Step 4: Add API methods** in `api.js`:

```js
telegramVerify(payload) { return this.post('/auth/telegram/verify', payload); },
telegramRegister(data) { return this.post('/auth/telegram/register', data); },
telegramLink(data) { return this.post('/auth/telegram/link', data); },
telegramVerify2fa(data) { return this.post('/auth/telegram/verify-2fa', data); },
```

- [ ] **Step 5: Add `TELEGRAM_BOT_USERNAME=` to `login/.env.example`** with a comment.

- [ ] **Step 6: Manual verification** — start XAMPP, open the login page, confirm the widget loads (or the fallback hint shows), and confirm the chooser appears after a mocked verify. Record limitations (widget needs HTTPS + BotFather domain).

- [ ] **Step 7: Checkpoint** — commit only if authorized.

---

### Task 13: Documentation (OpenAPI + bot contract)

**Files:**
- Modify: `openapi.yaml`, `docs/bot-host-api-contract.md`

- [ ] **Step 1: OpenAPI** — add the four `/auth/telegram/*` paths, request/response schemas, and the new error codes (`TELEGRAM_AUTH_INVALID`, `TELEGRAM_REPLAY`, `TELEGRAM_TICKET_INVALID`, `TELEGRAM_LINK_ROLE_UNSUPPORTED`, `RATE_LIMITED`). Document that `verify` never returns a token.

- [ ] **Step 2: Bot contract** — add a Telegram Login section: fixed `BOT_LOGIN_URL` button; `start=linked` handling; outbox `403` until the user opens the bot (unblocked on `/start`); BotFather `/setdomain` + HTTPS; `oauth.telegram.org` access caveat; mark `identity/lookup` + `identity/link` (`contact_verified`) as not used going forward; note the supporter `phone` backfill (migration 008) is now unnecessary and left untouched.

- [ ] **Step 3: Validate OpenAPI YAML** structurally (parse with PHP `yaml` unavailable → use `node` if present, else inspect). Report the method used.

- [ ] **Step 4: Checkpoint** — commit only if authorized.

---

### Task 14: Final verification

**Files:** all changed files.

- [ ] **Step 1: Run the full suite**: `php vendor/bin/phpunit` — expected PASS.
- [ ] **Step 2: Syntax-check each changed PHP file**: `php -l <file>`.
- [ ] **Step 3: Confirm success-path parity**: run the existing `AuthTest` login/register/verify-2fa assertions unchanged.
- [ ] **Step 4: Inspect `git status` and `git diff --check`**; confirm no unrelated changes and no secrets committed.
- [ ] **Step 5: Report evidence** (commands + observed output). Do not claim success without output.

---

## Self-review

- **Spec coverage:** §2 flows → Tasks 7–10; §3 security → Tasks 4, 9, 10; §4 contract → Tasks 7–10, 13; §5 throttle/IP → Tasks 2, 3, 9, 11; §6 data model → Task 1; §7 env → Tasks 1, 12; §8 code → Tasks 4–11; §9 bot docs → Task 13; §10 login page → Task 12; §11 tests → Tasks 2–11, 14.
- **Placeholder scan:** no TBD/TODO; code provided for new core files and critical service logic; modification tasks specify exact methods and invariants.
- **Type consistency:** `ClientIp::fromRequest`, `RateLimiter::{key,status,attempt,clear,prune}`, `TelegramTicket::{issue,validate,consume,jti}`, `TelegramLoginVerifier::{verify,canonicalHash}`, `AuthService::{createStudentAccount,sessionForUser,consume2faCode}`, `LinkService::linkVerified`, `TelegramAuthService::{verify,register,beginLink,completeLink2fa}` are used consistently across tasks.
