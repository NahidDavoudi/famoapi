<?php

namespace App\Core;

use Slim\Psr7\UploadedFile;
use Psr\Http\Message\ServerRequestInterface;

final class MultipartFormDataParser
{
    public static function applyToRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        $contentType = $request->getHeaderLine('Content-Type');
        if (!preg_match('/boundary=(?:"([^"]+)"|([^;]+))/i', $contentType, $matches)) {
            throw new ApiException('boundary درخواست multipart ارسال نشده است', 400, 'INVALID_MULTIPART');
        }

        $boundary = trim($matches[1] !== '' ? $matches[1] : $matches[2]);
        $multipart = self::parse((string) $request->getBody(), $boundary);
        return $request
            ->withParsedBody($multipart['fields'])
            ->withUploadedFiles($multipart['files']);
    }

    /**
     * @return array{fields: array<string, mixed>, files: array<string, UploadedFile>}
     */
    public static function parse(string $body, string $boundary): array
    {
        if ($boundary === '' || strlen($boundary) > 200 || !str_contains($body, '--' . $boundary)) {
            throw new ApiException('بدنه‌ی multipart معتبر نیست', 400, 'INVALID_MULTIPART');
        }

        $rawFields = [];
        $files = [];
        $parts = explode('--' . $boundary, $body);
        array_shift($parts);

        foreach ($parts as $part) {
            if (str_starts_with($part, '--')) {
                break;
            }

            $part = ltrim($part, "\r\n");
            if ($part === '') {
                continue;
            }

            $separator = strpos($part, "\r\n\r\n");
            if ($separator === false) {
                throw new ApiException('بخش multipart معتبر نیست', 400, 'INVALID_MULTIPART');
            }

            $headers = self::parseHeaders(substr($part, 0, $separator));
            $content = substr($part, $separator + 4);
            if (str_ends_with($content, "\r\n")) {
                $content = substr($content, 0, -2);
            }

            $disposition = $headers['content-disposition'] ?? '';
            $name = self::dispositionParameter($disposition, 'name');
            if ($name === null || !str_starts_with(strtolower($disposition), 'form-data')) {
                throw new ApiException('اطلاعات فرم multipart معتبر نیست', 400, 'INVALID_MULTIPART');
            }

            $filename = self::dispositionParameter($disposition, 'filename');
            if ($filename === null) {
                $rawFields[] = [$name, $content];
                continue;
            }

            if ($filename === '') {
                continue;
            }

            $temporaryPath = tempnam(sys_get_temp_dir(), 'famo-upload-');
            if ($temporaryPath === false || file_put_contents($temporaryPath, $content) === false) {
                if (is_string($temporaryPath) && is_file($temporaryPath)) {
                    unlink($temporaryPath);
                }
                throw new ApiException('ذخیره‌ی موقت فایل آپلودشده ممکن نشد', 500, 'UPLOAD_ERROR');
            }

            $files[$name] = new UploadedFile(
                $temporaryPath,
                basename(str_replace('\\', '/', $filename)),
                $headers['content-type'] ?? 'application/octet-stream',
                strlen($content),
                UPLOAD_ERR_OK,
                false
            );
        }

        return ['fields' => self::buildFields($rawFields), 'files' => $files];
    }

    private static function buildFields(array $rawFields): array
    {
        if ($rawFields === []) {
            return [];
        }

        $encoded = [];
        foreach ($rawFields as [$name, $value]) {
            $encoded[] = rawurlencode($name) . '=' . rawurlencode($value);
        }
        parse_str(implode('&', $encoded), $fields);

        return $fields;
    }

    private static function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            $separator = strpos($line, ':');
            if ($separator === false) {
                throw new ApiException('سرآیند multipart معتبر نیست', 400, 'INVALID_MULTIPART');
            }
            $name = strtolower(trim(substr($line, 0, $separator)));
            $headers[$name] = trim(substr($line, $separator + 1));
        }

        return $headers;
    }

    private static function dispositionParameter(string $disposition, string $parameter): ?string
    {
        $pattern = '/(?:^|;)\s*' . preg_quote($parameter, '/') . '\s*=\s*(?:"((?:[^"\\\\]|\\\\.)*)"|([^;\s]*))/i';
        if (!preg_match($pattern, $disposition, $matches)) {
            return null;
        }

        $value = $matches[1] !== '' ? stripcslashes($matches[1]) : ($matches[2] ?? '');
        return $value;
    }
}
