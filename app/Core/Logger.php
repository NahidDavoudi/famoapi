<?php
declare(strict_types=1);

namespace App\Core;

use JsonSerializable;
use Throwable;

/**
 * Minimal file logger. No external dependencies.
 * Writes to storage/logs/app.log; falls back to error_log() on failure.
 *
 * نکته: logging هرگز نباید درخواست رو کرش کنه. تمام متدها safe هستن.
 */
final class Logger
{
    private static string $path = '';

    public static function boot(?string $path = null): void
    {
        self::$path = $path ?? self::defaultPath();
    }

    /** @param array<string,mixed> $context */
    public static function debug(string $message, array $context = []): void
    {
        self::write('DEBUG', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function redact(string $text): string
    {
        // توکن ربات تلگرام: <digits>:<35-char base64ish>
        $text = preg_replace(
            '#\b\d{6,12}:[A-Za-z0-9_\-]{30,}#',
            '[TG_TOKEN_REDACTED]',
            $text
        ) ?? $text;

        // کلید سرویس ربات
        $text = preg_replace(
            '#(X-Bot-Key[\s:]+)[A-Za-z0-9_\-]{20,}#i',
            '$1[REDACTED]',
            $text
        ) ?? $text;

        return $text;
    }

    private static function write(string $level, string $message, array $context): void
    {
        try {
            $message = self::redact($message);
            $context = self::scrub($context);

            $line = sprintf(
                '[%s] %s: %s%s',
                date('Y-m-d H:i:s'),
                $level,
                $message,
                $context !== []
                    ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : ''
            );

            $path = self::$path !== '' ? self::$path : self::defaultPath();
            $dir = dirname($path);

            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }

            if (is_dir($dir) && is_writable($dir)) {
                @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
            } else {
                error_log($line);
            }
        } catch (Throwable) {
            // logging must never break the request
        }
    }

    private static function scrub(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::redact($value);
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::scrub($v);
            }
            return $out;
        }

        if ($value instanceof Throwable) {
            return [
                'class'   => get_class($value),
                'message' => self::redact($value->getMessage()),
                'file'    => $value->getFile(),
                'line'    => $value->getLine(),
            ];
        }

        if ($value instanceof JsonSerializable) {
            return self::scrub($value->jsonSerialize());
        }

        if (is_object($value)) {
            return ['_class' => get_class($value)];
        }

        return $value;
    }

    private static function defaultPath(): string
    {
        return dirname(__DIR__, 2) . '/storage/logs/app.log';
    }
}