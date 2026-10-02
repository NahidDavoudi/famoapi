<?php

namespace App\Modules\Auth;

use App\Core\ApiException;
use App\Core\Database;

/**
 * Verifies Telegram Login Widget payloads offline (no call to Telegram) and
 * enforces one-time use of each verified payload.
 *
 * The API stores only TELEGRAM_LOGIN_SECRET_KEY = hex(SHA256(bot_token)).
 */
class TelegramLoginVerifier
{
    private const DEFAULT_MAX_AGE = 300;
    private const INVALID = 'TELEGRAM_AUTH_INVALID';

    /**
     * @return array{id:int,username:?string,first_name:?string,last_name:?string,photo_url:?string}
     */
    public static function verify(array $payload): array
    {
        $hash = $payload['hash'] ?? null;
        if (!is_string($hash) || $hash === '') {
            throw new ApiException('Telegram auth payload is invalid', 401, self::INVALID);
        }

        $authDate = (int) ($payload['auth_date'] ?? 0);
        if ($authDate <= 0) {
            throw new ApiException('Telegram auth payload is invalid', 401, self::INVALID);
        }

        $maxAge = (int) ($_ENV['TELEGRAM_LOGIN_MAX_AGE'] ?? self::DEFAULT_MAX_AGE);
        if (time() - $authDate > $maxAge) {
            throw new ApiException('Telegram auth payload is invalid', 401, self::INVALID);
        }

        $secretHex = $_ENV['TELEGRAM_LOGIN_SECRET_KEY'] ?? '';
        if (!is_string($secretHex) || $secretHex === '') {
            throw new \RuntimeException('TELEGRAM_LOGIN_SECRET_KEY is not configured', 500);
        }
        $secret = hex2bin($secretHex);
        if ($secret === false) {
            throw new \RuntimeException('TELEGRAM_LOGIN_SECRET_KEY is not valid hex', 500);
        }

        $computed = hash_hmac('sha256', self::dataCheckString($payload), $secret);
        if (!hash_equals($computed, $hash)) {
            throw new ApiException('Telegram auth payload is invalid', 401, self::INVALID);
        }

        self::recordNonce(self::canonicalHash($payload), $maxAge);

        return [
            'id'         => (int) ($payload['id'] ?? 0),
            'username'   => self::nullableString($payload['username'] ?? null),
            'first_name' => self::nullableString($payload['first_name'] ?? null),
            'last_name'  => self::nullableString($payload['last_name'] ?? null),
            'photo_url'  => self::nullableString($payload['photo_url'] ?? null),
        ];
    }

    public static function canonicalHash(array $payload): string
    {
        return hash('sha256', self::dataCheckString($payload));
    }

    private static function dataCheckString(array $payload): string
    {
        $pairs = [];
        foreach ($payload as $key => $value) {
            if ($key === 'hash' || !is_scalar($value)) {
                continue;
            }
            $pairs[(string) $key] = (string) $value;
        }
        ksort($pairs, SORT_STRING);

        $lines = [];
        foreach ($pairs as $key => $value) {
            $lines[] = $key . '=' . $value;
        }

        return implode("\n", $lines);
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function recordNonce(string $nonce, int $ttl): void
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO telegram_auth_nonces (kind, nonce, expires_at)
             VALUES (:kind, :nonce, :expires_at)'
        );

        try {
            $stmt->execute([
                'kind'       => 'payload',
                'nonce'      => $nonce,
                'expires_at' => gmdate('Y-m-d H:i:s', time() + $ttl),
            ]);
        } catch (\PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                throw new ApiException('Telegram auth payload is replayed', 409, 'TELEGRAM_REPLAY', $e);
            }
            throw $e;
        }
    }
}
