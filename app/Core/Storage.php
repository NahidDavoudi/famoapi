<?php

namespace App\Core;

class Storage
{
    private static array $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    private static int $maxSize = 10 * 1024 * 1024; // 10MB

    public static function upload(array $file, string $module, ?int $ownerId = null): string
    {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, self::$allowedExtensions)) {
            throw new \RuntimeException('نوع فایل مجاز نیست: ' . $extension, 400);
        }

        if ($file['size'] > self::$maxSize) {
            throw new \RuntimeException('حجم فایل نباید بیشتر از 10 مگابایت باشد', 400);
        }

        $basePath = $_ENV['UPLOADS_PATH'] ?? __DIR__ . '/../../uploads';
        $subDir = $module;
        if ($ownerId) {
            $subDir .= '/' . $ownerId;
        }

        $dir = rtrim($basePath, '/') . '/' . $subDir;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = uniqid() . '.' . $extension;
        $destination = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException('خطا در آپلود فایل', 500);
        }

        return $subDir . '/' . $filename;
    }

    public static function delete(string $path): void
    {
        $basePath = $_ENV['UPLOADS_PATH'] ?? __DIR__ . '/../../uploads';
        $fullPath = rtrim($basePath, '/') . '/' . ltrim($path, '/');

        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}