<?php

namespace App\Core;

final class AssetUrl
{
    public static function from(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }
        $base = rtrim((string) ($_ENV['ASSET_URL'] ?? ''), '/');
        return $base . '/' . ltrim($path, '/');
    }

    /**
     * برای مسیرهایی که از Storage::upload() برمی‌گردند و
     * نسبت به پوشه‌ی uploads هستند (مثلاً "instructors/abc.webp").
     */
    public static function fromStorage(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }

        $cleanPath = ltrim(str_replace('\\', '/', $path), '/');
        if (!str_starts_with($cleanPath, 'uploads/')) {
            $cleanPath = 'uploads/' . $cleanPath;
        }

        $base = rtrim((string) ($_ENV['ASSET_URL'] ?? ''), '/');
        return $base . '/' . $cleanPath;
    }
}