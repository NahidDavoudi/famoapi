<?php

namespace App\Core;

/**
 * تبدیل و بهینه‌سازی تصاویر به WebP.
 * اولویت: Imagick → GD → بدون تبدیل.
 */
final class ImageOptimizer
{
    public static function isAvailable(): bool
    {
        return extension_loaded('imagick') || extension_loaded('gd');
    }

    /**
     * تبدیل یک فایل تصویری به WebP با resize اختیاری.
     *
     * @param array{quality?:int,max_width?:int,max_height?:int} $options
     */
    public static function toWebp(string $sourcePath, string $destinationPath, array $options = []): bool
    {
        if (!is_file($sourcePath)) {
            return false;
        }

        $quality  = max(1, min(100, (int) ($options['quality']   ?? 82)));
        $maxWidth = max(1, (int) ($options['max_width']  ?? 1920));
        $maxHeight= max(1, (int) ($options['max_height'] ?? 1920));

        $info = @getimagesize($sourcePath);
        if ($info === false) {
            return false;
        }
        $mime = $info['mime'] ?? '';

        // JFIF files report as image/jpeg, so this already covers .jfif uploads.
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return false;
        }

        if (extension_loaded('imagick')) {
            return self::toWebpImagick($sourcePath, $destinationPath, $quality, $maxWidth, $maxHeight);
        }
        if (extension_loaded('gd')) {
            return self::toWebpGd($sourcePath, $destinationPath, $mime, $quality, $maxWidth, $maxHeight);
        }

        return false;
    }

    private static function toWebpGd(
        string $src,
        string $dst,
        string $mime,
        int $quality,
        int $maxW,
        int $maxH
    ): bool {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($src),
            'image/png'  => @imagecreatefrompng($src),
            'image/webp' => @imagecreatefromwebp($src),
            default      => false,
        };
        if ($image === false) {
            return false;
        }

        $w = imagesx($image);
        $h = imagesy($image);

        if ($w > $maxW || $h > $maxH) {
            $ratio = min($maxW / $w, $maxH / $h);
            $newW  = max(1, (int) round($w * $ratio));
            $newH  = max(1, (int) round($h * $ratio));
            $resized = @imagescale($image, $newW, $newH, IMG_BICUBIC);
            if ($resized !== false) {
                imagedestroy($image);
                $image = $resized;
            }
        }

        // حفظ شفافیت PNG
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $ok = @imagewebp($image, $dst, $quality);
        imagedestroy($image);

        return (bool) $ok;
    }

    private static function toWebpImagick(
        string $src,
        string $dst,
        int $quality,
        int $maxW,
        int $maxH
    ): bool {
        try {
            $im = new \Imagick($src);
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality($quality);

            if ($im->getImageWidth() > $maxW || $im->getImageHeight() > $maxH) {
                $im->thumbnailImage($maxW, $maxH, true, true);
            }

            $im->stripImage(); // حذف متادیتا
            $ok = $im->writeImage($dst);
            $im->clear();
            $im->destroy();

            return (bool) $ok;
        } catch (\Throwable $e) {
            return false;
        }
    }
}