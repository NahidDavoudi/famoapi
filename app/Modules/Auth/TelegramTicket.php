<?php

namespace App\Modules\Auth;

use App\Core\ApiException;
use App\Core\Auth;
use App\Core\Database;

/**
 * Issues and consumes short-lived single-use tickets that hand a verified
 * Telegram identity to the link/register endpoints. Tickets are signed with a
 * key derived from JWT_SECRET so they can never be confused with session JWTs.
 */
class TelegramTicket
{
    private const AUDIENCE = 'tg_link';
    private const PURPOSE = 'tg_link';
    private const KEY_CONTEXT = 'famo.telegram.link.ticket.v1';
    private const DEFAULT_TTL = 600;

    private static function signingKey(): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if ($secret === '') {
            throw new \RuntimeException('JWT_SECRET is not configured', 500);
        }

        return hash_hmac('sha256', self::KEY_CONTEXT, $secret, true);
    }

    public static function issue(int $telegramUserId, ?string $telegramUsername): string
    {
        $ttl = (int) ($_ENV['TELEGRAM_AUTH_TICKET_TTL'] ?? self::DEFAULT_TTL);
        $jti = bin2hex(random_bytes(16));

        $stmt = Database::getConnection()->prepare(
            'INSERT INTO telegram_auth_nonces (kind, nonce, expires_at)
             VALUES (:kind, :nonce, :expires_at)'
        );
        $stmt->execute([
            'kind'       => 'ticket',
            'nonce'      => $jti,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + $ttl),
        ]);

        return Auth::encodeWithKey([
            'aud'         => self::AUDIENCE,
            'purpose'     => self::PURPOSE,
            'jti'         => $jti,
            'tg_user_id'  => $telegramUserId,
            'tg_username' => $telegramUsername,
        ], self::signingKey(), $ttl);
    }

    public static function validate(string $ticket): object
    {
        $key = self::signingKey();

        try {
            $claims = Auth::decodeWithKey($ticket, $key);
        } catch (\Throwable $e) {
            throw new ApiException('Telegram ticket is invalid', 401, 'TELEGRAM_TICKET_INVALID', $e);
        }

        if (($claims->aud ?? null) !== self::AUDIENCE || ($claims->purpose ?? null) !== self::PURPOSE) {
            throw new ApiException('Telegram ticket is invalid', 401, 'TELEGRAM_TICKET_INVALID');
        }

        return $claims;
    }

    public static function consume(string $ticket): object
    {
        $claims = self::validate($ticket);
        $jti = self::jti($claims);

        $stmt = Database::getConnection()->prepare(
            "UPDATE telegram_auth_nonces
             SET consumed_at = UTC_TIMESTAMP()
             WHERE kind = 'ticket' AND nonce = :jti
               AND consumed_at IS NULL AND expires_at > UTC_TIMESTAMP()"
        );
        $stmt->execute(['jti' => $jti]);

        if ($stmt->rowCount() !== 1) {
            throw new ApiException('Telegram ticket is invalid', 401, 'TELEGRAM_TICKET_INVALID');
        }

        return $claims;
    }

    public static function jti(object $claims): string
    {
        return (string) ($claims->jti ?? '');
    }
}
