<?php
declare(strict_types=1);

namespace App\Core;

/**
 * کش فایلی ساده. ساختار: {dir}/{group}/{md5(key)}.cache
 */
class Cache
{
    private static string $dir = '';
    private static bool $enabled = true;

    public static function configure(string $dir, bool $enabled = true): void
    {
        self::$dir = rtrim($dir, '/\\');
        self::$enabled = $enabled;

        if ($enabled && !is_dir(self::$dir)) {
            @mkdir(self::$dir, 0775, true);
        }
    }

    /** کش hit → مقدار کش‌شده؛ miss → اجرای callback و ذخیره. نتیجه‌ی null هرگز ذخیره نمی‌شود. */
    public static function remember(string $group, string $key, int $ttl, callable $callback): mixed
    {
        if (!self::$enabled) {
            return $callback();
        }

        $hit = self::read($group, $key);
        if ($hit !== null) {
            return $hit['value'];
        }

        $value = $callback();

        if ($value !== null) {
            self::write($group, $key, $value, $ttl);
        }

        return $value;
    }

    public static function forget(string $group, string $key): void
    {
        @unlink(self::path($group, $key));
    }

    public static function flushGroup(string $group): void
    {
        $dir = self::groupDir($group);
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*.cache') ?: [] as $file) {
            @unlink($file);
        }
    }

    public static function purgeExpired(): int
    {
        $removed = 0;
        foreach (glob(self::$dir . '/*/*.cache') ?: [] as $file) {
            $raw = @file_get_contents($file);
            $data = $raw === false ? null : @unserialize($raw, ['allowed_classes' => false]);
            if (!is_array($data) || $data['exp'] < time()) {
                @unlink($file);
                $removed++;
            }
        }
        return $removed;
    }

    private static function read(string $group, string $key): ?array
    {
        $file = self::path($group, $key);
        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        $data = $raw === false ? null : @unserialize($raw, ['allowed_classes' => false]);

        if (!is_array($data) || !isset($data['exp']) || $data['exp'] < time()) {
            @unlink($file);
            return null;
        }

        return $data;
    }

    private static function write(string $group, string $key, mixed $value, int $ttl): void
    {
        $dir = self::groupDir($group);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return; // شکست در نوشتن کش نباید برنامه را خراب کند
        }

        $file = self::path($group, $key);
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (@file_put_contents($tmp, serialize(['exp' => time() + $ttl, 'value' => $value])) !== false) {
            @rename($tmp, $file); // نوشتن اتمیک
        }
    }

    private static function groupDir(string $group): string
    {
        return self::$dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $group);
    }

    private static function path(string $group, string $key): string
    {
        return self::groupDir($group) . '/' . md5($key) . '.cache';
    }
}
