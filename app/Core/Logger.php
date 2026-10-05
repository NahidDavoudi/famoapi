<?php
declare(strict_types=1);

namespace App\Core;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;

final class Logger
{
    private static ?MonologLogger $logger = null;

    private static string $path = '';

    public static function boot(?string $path = null): void
    {
        self::$path = $path ?? self::defaultPath();
        self::$logger = null;
    }

    public static function channel(): MonologLogger
    {
        if (self::$logger instanceof MonologLogger) {
            return self::$logger;
        }

        $path = self::$path !== '' ? self::$path : self::defaultPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        // debug توی production خیلی حرف می‌زنه؛ فقط local
        $isLocal = (($_ENV['APP_ENV'] ?? 'prod') === 'local');
        $level = $isLocal ? Level::Debug : Level::Warning;

        $handler = new RotatingFileHandler($path, 14, $level);
        $handler->setFormatter(new LineFormatter(null, null, true, true));

        return self::$logger = new MonologLogger('bot', [$handler]);
    }

    /** @param array<string,mixed> $context */
    public static function debug(string $message, array $context = []): void
    {
        self::channel()->debug(self::redact($message), self::scrub($context));
    }

    /** @param array<string,mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::channel()->info(self::redact($message), self::scrub($context));
    }

    /** @param array<string,mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::channel()->warning(self::redact($message), self::scrub($context));
    }

    /** @param array<string,mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::channel()->error(self::redact($message), self::scrub($context));
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

    private static function scrub(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::redact($value);
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = self::scrub($item);
            }

            return $out;
        }

        return $value;
    }

    private static function defaultPath(): string
    {
        return dirname(__DIR__, 2) . '/storage/logs/bot.log';
    }
}