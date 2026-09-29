<?php

namespace App\Core;

use finfo;
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
            throw new ApiException('خطا در دریافت فایل', 400, 'UPLOAD_ERROR');
        }
        if (!in_array($extension, self::$allowedExtensions, true)) {
            throw new ApiException('نوع فایل مجاز نیست', 422, 'UPLOAD_ERROR');
        }
        if ($size > self::$maxSize) {
            throw new ApiException('حجم فایل نباید بیشتر از 10 مگابایت باشد', 413, 'UPLOAD_ERROR');
        }

        $basePath = $_ENV['UPLOADS_PATH'] ?? __DIR__ . '/../../uploads';
        $temporaryPath = $file instanceof UploadedFileInterface
            ? $file->getStream()->getMetadata('uri')
            : ($file['tmp_name'] ?? null);
        if (!is_string($temporaryPath) || !is_file($temporaryPath)) {
            throw new ApiException('محتوای فایل در دسترس نیست', 422, 'UPLOAD_ERROR');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $allowedMimeTypes = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/CDFV2'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        ];
        if (!in_array($mime, $allowedMimeTypes[$extension] ?? [], true)) {
            throw new ApiException('محتوای فایل با پسوند آن مطابقت ندارد', 422, 'UPLOAD_ERROR');
        }

        $subDir = $module;
        if ($ownerId) {
            $subDir .= '/' . $ownerId;
        }

        $dir = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $subDir;
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new ApiException('مسیر ذخیره فایل در دسترس نیست', 500, 'UPLOAD_ERROR');
            }
        }

        $filename = uniqid() . '.' . $extension;
        $destination = $dir . '/' . $filename;

        if ($file instanceof UploadedFileInterface) {
            $file->moveTo($destination);
        } elseif (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new ApiException('خطا در آپلود فایل', 500, 'UPLOAD_ERROR');
        }

        return $subDir . '/' . $filename;
    }

    public static function resolve(string $path): string
    {
        $basePath = realpath($_ENV['UPLOADS_PATH'] ?? __DIR__ . '/../../uploads');
        if ($basePath === false) {
            throw new ApiException('مسیر فایل‌ها در دسترس نیست', 500, 'STORAGE_ERROR');
        }
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $fullPath = realpath($basePath . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR));
        if ($fullPath === false || !self::isWithinBase($basePath, $fullPath) || !is_file($fullPath)) {
            throw new ApiException('فایل یافت نشد', 404, 'NOT_FOUND');
        }
        return $fullPath;
    }

    public static function delete(string $path): void
    {
        $basePath = realpath($_ENV['UPLOADS_PATH'] ?? __DIR__ . '/../../uploads');
        if ($basePath === false) {
            return;
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $fullPath = realpath($basePath . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR));
        if ($fullPath !== false && self::isWithinBase($basePath, $fullPath) && is_file($fullPath)) {
            unlink($fullPath);
        }
    }

    private static function isWithinBase(string $basePath, string $fullPath): bool
    {
        $basePrefix = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($fullPath, $basePrefix);
    }
}
