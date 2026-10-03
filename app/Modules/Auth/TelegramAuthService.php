<?php
declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\ApiException;
use App\Core\Database;
use App\Core\RateLimitedException;
use App\Core\RateLimiter;
use App\Core\SmsService;
use App\Modules\Bot\BotActor;
use App\Modules\Bot\TelegramLink;
use App\Modules\Linking\LinkService;

/**
 * Orchestrates the Telegram Login Widget handoff: verify, register, account
 * linking (student direct / supporter 2FA), and the bound 2FA challenge.
 *
 * Security invariants enforced here:
 * - verify never issues a session token.
 * - Tickets are single-use and consumed only when the link row is created.
 * - Supporters and admins must clear an SMS 2FA challenge before any link row exists.
 * - Conflict errors are only surfaced after successful authentication.
 */
class TelegramAuthService
{
    private AuthService $auth;
    private LinkService $links;
    private SmsService $sms;

    public function __construct(?AuthService $auth = null, ?LinkService $links = null, ?SmsService $sms = null)
    {
        $this->auth = $auth ?? new AuthService();
        $this->links = $links ?? new LinkService();
        $this->sms = $sms ?? new SmsService();
    }

    public function verify(array $payload, ?string $ip = null): array
    {
        $this->throttleIp('tg:verify:ip', $ip, 'AUTH_TELEGRAM_VERIFY_PER_IP', 30, 60);

        $tg = TelegramLoginVerifier::verify($payload);

        $this->throttleValue('tg:verify:tg', (string) $tg['id'], 'AUTH_TELEGRAM_VERIFY_PER_TG', 10, 60);

        foreach (BotActor::ROLES as $role) {
            if (TelegramLink::findByTelegramUserAndRole((int) $tg['id'], $role)) {
                return ['linked' => true, 'bot_redirect_url' => $this->botRedirect()];
            }
        }

        return [
            'linked'   => false,
            'ticket'   => TelegramTicket::issue((int) $tg['id'], $tg['username']),
            'telegram' => $tg,
        ];
    }

    public function register(array $data, string $ticket, ?string $ip = null): array
    {
        $this->throttleIp('tg:register:ip', $ip, 'AUTH_TELEGRAM_REGISTER_PER_IP', 10, 3600);

        $claims = TelegramTicket::validate($ticket);
        $tgUserId = (int) ($claims->tg_user_id ?? 0);

        $this->throttleValue('tg:register:tg', (string) $tgUserId, 'AUTH_TELEGRAM_REGISTER_PER_TG', 10, 3600);

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            TelegramTicket::consume($ticket);

            $account = $this->auth->createStudentAccount($data);

            $this->links->linkVerified('student', (int) $account['studentId'], $tgUserId, $tgUserId);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $this->auth->sessionForUser((int) $account['userId'])
            + ['bot_redirect_url' => $this->botRedirect()];
    }

    public function beginLink(string $ticket, string $username, string $password, ?string $ip = null): array
    {
        $this->throttleLink($username, $ip);

        $claims = TelegramTicket::validate($ticket);

        $user = User::findByUsername($username);
        if (!$user || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            throw new ApiException('نام کاربری یا رمز عبور اشتباه است', 401, 'AUTH_ERROR');
        }
        if (isset($user['is_active']) && (int) $user['is_active'] === 0) {
            throw new ApiException('حساب کاربری غیرفعال است', 403, 'FORBIDDEN');
        }

        $role = (string) $user['role'];
        if (!in_array($role, ['student', 'supporter' , 'admin'], true)) {
            throw new ApiException('اتصال تلگرام برای این نقش پشتیبانی نمی‌شود', 403, 'TELEGRAM_LINK_ROLE_UNSUPPORTED');
        }

        $tgUserId = (int) ($claims->tg_user_id ?? 0);

        if ($role === 'supporter' || $role === 'admin') {
            $challenge = $this->issueSmsChallenge($claims, (int) $user['id'], (string) $user['username'], $ip);

            return [
                'requires_2fa' => true,
                'challenge_id' => $challenge['nonce'],
                'phone_mask'   => $challenge['mask'],
            ];
        }

        $accountId = (int) ($user['linked_id'] ?? 0);

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            TelegramTicket::consume($ticket);
            $result = $this->links->linkVerified($role, $accountId, $tgUserId, $tgUserId);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        if ($ip !== null && $ip !== '') {
            RateLimiter::clear(RateLimiter::key('login:user_ip', $username . '|' . $ip));
        }

        return $this->auth->sessionForUser((int) $user['id']) + [
            'link'             => $result,
            'bot_redirect_url' => $this->botRedirect(),
        ];
    }

    public function completeLink2fa(
        string $ticket,
        string $challengeId,
        string $code,
        ?int $clientUserId,
        ?string $ip = null
    ): array {
        $this->throttleIp('tg:link:ip', $ip, 'AUTH_LOGIN_IP_MAX_ATTEMPTS', 30, 900);

        $claims = TelegramTicket::validate($ticket);

        $challenge = $this->loadChallenge($challengeId);

        if ((string) $challenge['ticket_jti'] !== TelegramTicket::jti($claims)) {
            throw new ApiException('کد تأیید نامعتبر یا منقضی شده است', 401, '2FA_ERROR');
        }
        if ($clientUserId !== null && $clientUserId !== (int) $challenge['user_id']) {
            throw new ApiException('کد تأیید نامعتبر یا منقضی شده است', 401, '2FA_ERROR');
        }

        $maxAttempts = max(1, (int) ($_ENV['AUTH_2FA_MAX_ATTEMPTS'] ?? 5));
        $ttl = max(1, (int) ($_ENV['AUTH_2FA_CHALLENGE_TTL'] ?? 300));
        $attemptKey = RateLimiter::key('2fa:chal', $challengeId);
        if (RateLimiter::attempt($attemptKey, $maxAttempts, $ttl, null)['allowed'] === false) {
            throw new RateLimitedException($ttl);
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $user = $this->auth->consume2faCode((int) $challenge['user_id'], $code);

            $role = (string) $user['role'];
            if (!in_array($role, ['student', 'supporter' , 'admin'], true)) {
                throw new ApiException('اتصال تلگرام برای این نقش پشتیبانی نمی‌شود', 403, 'TELEGRAM_LINK_ROLE_UNSUPPORTED');
            }

            $tgUserId = (int) ($claims->tg_user_id ?? 0);
            $accountId = (int) ($user['linked_id'] ?? 0);

            $this->links->linkVerified($role, $accountId, $tgUserId, $tgUserId);
            TelegramTicket::consume($ticket);
            $this->consumeChallenge($challengeId);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        if ($ip !== null && $ip !== '') {
            RateLimiter::clear(RateLimiter::key('login:user_ip', (string) $user['username'] . '|' . $ip));
        }

        return $this->auth->sessionForUser((int) $user['id'])
            + ['bot_redirect_url' => $this->botRedirect()];
    }

    /**
     * @param object $claims validated ticket claims
     * @return array{nonce:string,mask:string}
     */
    private function issueSmsChallenge(object $claims, int $userId, string $phone, ?string $ip): array
    {
        $phoneKey = RateLimiter::key('sms:phone', $phone);
        $phoneMax = max(1, (int) ($_ENV['AUTH_SMS_PHONE_HOURLY_MAX'] ?? 5));
        $phoneStatus = RateLimiter::attempt($phoneKey, $phoneMax, 3600, 3600);
        if (!$phoneStatus['allowed']) {
            throw new RateLimitedException((int) ($phoneStatus['retry_after'] ?: 3600));
        }

        if ($ip !== null && $ip !== '') {
            $ipKey = RateLimiter::key('sms:ip', $ip);
            $ipMax = max(1, (int) ($_ENV['AUTH_SMS_IP_HOURLY_MAX'] ?? 20));
            $ipStatus = RateLimiter::attempt($ipKey, $ipMax, 3600, 3600);
            if (!$ipStatus['allowed']) {
                throw new RateLimitedException((int) ($ipStatus['retry_after'] ?: 3600));
            }
        }

        $db = Database::getConnection();
        $ttl = max(1, (int) ($_ENV['AUTH_2FA_CHALLENGE_TTL'] ?? 300));
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $ttl);

        $db->prepare('UPDATE login_codes SET used = 1 WHERE user_id = ? AND used = 0')->execute([$userId]);
        $db->prepare('INSERT INTO login_codes (user_id, code, expires_at) VALUES (?, ?, ?)')
           ->execute([$userId, $code, $expiresAt]);

        $nonce = bin2hex(random_bytes(16));
        $db->prepare(
            'INSERT INTO telegram_2fa_challenges (challenge_nonce, ticket_jti, user_id, expires_at)
             VALUES (?, ?, ?, ?)'
        )->execute([$nonce, TelegramTicket::jti($claims), $userId, $expiresAt]);

        $this->sms->sendVerificationCode($phone, $code);

        return [
            'nonce' => $nonce,
            'mask'  => substr($phone, 0, 4) . '***' . substr($phone, -2),
        ];
    }

    private function loadChallenge(string $challengeId): array
    {
        if ($challengeId === '') {
            throw new ApiException('کد تأیید نامعتبر یا منقضی شده است', 401, '2FA_ERROR');
        }

        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM telegram_2fa_challenges WHERE challenge_nonce = :n LIMIT 1'
        );
        $stmt->execute(['n' => $challengeId]);
        $row = $stmt->fetch();

        if (!$row || $row['consumed_at'] !== null) {
            throw new ApiException('کد تأیید نامعتبر یا منقضی شده است', 401, '2FA_ERROR');
        }
        if (strtotime((string) $row['expires_at'] . ' UTC') <= time()) {
            throw new ApiException('کد تأیید نامعتبر یا منقضی شده است', 401, '2FA_ERROR');
        }

        return $row;
    }

    private function consumeChallenge(string $challengeId): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE telegram_2fa_challenges SET consumed_at = UTC_TIMESTAMP()
             WHERE challenge_nonce = :n AND consumed_at IS NULL'
        );
        $stmt->execute(['n' => $challengeId]);

        if ($stmt->rowCount() !== 1) {
            throw new ApiException('کد تأیید نامعتبر یا منقضی شده است', 401, '2FA_ERROR');
        }
    }

    private function throttleIp(string $scope, ?string $ip, string $env, int $default, int $window): void
    {
        if ($ip === null || $ip === '') {
            return;
        }

        $max = max(1, (int) ($_ENV[$env] ?? $default));
        $status = RateLimiter::attempt(RateLimiter::key($scope, $ip), $max, $window, $window);
        if (!$status['allowed']) {
            throw new RateLimitedException((int) ($status['retry_after'] ?: $window));
        }
    }

    private function throttleValue(string $scope, string $value, string $env, int $default, int $window): void
    {
        if ($value === '') {
            return;
        }

        $max = max(1, (int) ($_ENV[$env] ?? $default));
        $status = RateLimiter::attempt(RateLimiter::key($scope, $value), $max, $window, $window);
        if (!$status['allowed']) {
            throw new RateLimitedException((int) ($status['retry_after'] ?: $window));
        }
    }

    private function throttleLink(string $username, ?string $ip): void
    {
        if ($ip === null || $ip === '') {
            return;
        }

        $window = max(1, (int) ($_ENV['AUTH_LOGIN_WINDOW_SECONDS'] ?? 900));
        $this->throttleIp('tg:link:ip', $ip, 'AUTH_LOGIN_IP_MAX_ATTEMPTS', 30, $window);

        $max = max(1, (int) ($_ENV['AUTH_LOGIN_MAX_ATTEMPTS'] ?? 5));
        $lockout = max(1, (int) ($_ENV['AUTH_LOGIN_LOCKOUT_SECONDS'] ?? 900));
        $status = RateLimiter::attempt(RateLimiter::key('login:user_ip', $username . '|' . $ip), $max, $window, $lockout);
        if (!$status['allowed']) {
            throw new RateLimitedException((int) ($status['retry_after'] ?: $lockout));
        }
    }

    private function botRedirect(): ?string
    {
        $base = trim((string) ($_ENV['TELEGRAM_BOT_URL'] ?? ''));

        return $base === '' ? null : rtrim($base, '/') . '?start=linked';
    }
}
