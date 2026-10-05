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

        $handler = new RotatingFileHandler($path, 14, Level::Debug);
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
        return preg_replace('#bot\d+:[A-Za-z0-9_\-]+#', 'bot[REDACTED]', $text) ?? $text;
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
