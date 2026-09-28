<?php

namespace App\Core;

use Psr\Http\Message\UploadedFileInterface;

class Storage
{
    private static array $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    private static int $maxSize = 10 * 1024 * 1024; // 10MB

    public static function upload(array|UploadedFileInterface $file, string $module, ?int $ownerId = null): string
    {
        if ($file instanceof UploadedFileInterface) {
            $name = $file->getClientFilename() ?? '';
            $size = $file->getSize() ?? 0;
            $error = $file->getError();
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        } else {
            $name = $file['name'] ?? '';
            $size = (int) ($file['size'] ?? 0);
            $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('خطا در دریافت فایل', 400);
        }
        if (!in_array($extension, self::$allowedExtensions, true)) {
            throw new \RuntimeException('نوع فایل مجاز نیست: ' . $extension, 400);
        }
        if ($size > self::$maxSize) {
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

        if ($file instanceof UploadedFileInterface) {
            $file->moveTo($destination);
        } elseif (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException('خطا در آپلود فایل', 500);
        }

        return $subDir . '/' . $filename;
    }

    public static function resolve(string $path): string
    {
        $basePath = realpath($_ENV['UPLOADS_PATH'] ?? __DIR__ . '/../../uploads');
        if ($basePath === false) {
            throw new \RuntimeException('مسیر فایل‌ها در دسترس نیست', 500);
        }
        $fullPath = realpath($basePath . '/' . ltrim($path, '/'));
        if ($fullPath === false || !str_starts_with($fullPath, $basePath . DIRECTORY_SEPARATOR) || !is_file($fullPath)) {
            throw new \RuntimeException('فایل یافت نشد', 404);
        }
        return $fullPath;
    }

    public static function delete(string $path): void
    {
        $basePath = realpath($_ENV['UPLOADS_PATH'] ?? __DIR__ . '/../../uploads');
        if ($basePath === false) {
            return;
        }

        $fullPath = realpath($basePath . '/' . ltrim($path, '/'));
        if ($fullPath !== false && str_starts_with($fullPath, $basePath . DIRECTORY_SEPARATOR) && is_file($fullPath)) {
            unlink($fullPath);
        }
    }
}