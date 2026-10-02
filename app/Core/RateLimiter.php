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
