<?php

namespace App\Modules\Files;

use App\Core\Pagination;
use App\Core\Storage;
use Psr\Http\Message\UploadedFileInterface;

class FileService
{
    public function list(?int $studentId, int $page, int $perPage): array
    {
        $total = File::countAll($studentId);
        $pagination = Pagination::build($page, $perPage, $total);
        $items = File::findAll($studentId, $pagination['page'], $pagination['per_page']);

        return [
            'items'      => $items,
            'pagination' => $pagination,
        ];
    }

    public function upload(UploadedFileInterface $uploadedFile, int $studentId, ?string $description, ?string $examDate = null): array
    {
        $filePath = Storage::upload($uploadedFile, 'exams', $studentId);

        $reportDate = $this->normalizeDate($examDate);
        if ($examDate !== null && $examDate !== '' && $reportDate === null) {
            Storage::delete($filePath);
            throw new \App\Core\ApiException('تاریخ آزمون نامعتبر است', 422, 'VALIDATION_ERROR');
        }
        $reportDate ??= date('Y-m-d');
        $fileType = ($examDate !== null && $examDate !== '') ? 'exam' : 'other';

        try {
            $fileId = File::create([
                'owner_type'  => 'student',
                'owner_id'    => $studentId,
                'file_type'   => $fileType,
                'report_date' => $reportDate,
                'file_path'   => $filePath,
                'file_size'   => $uploadedFile->getSize() ?? 0,
                'description' => $description,
            ]);

            $file = File::findById($fileId);
            if (!$file) {
                throw new \App\Core\ApiException('خطا در ذخیره اطلاعات فایل', 500, 'INTERNAL_ERROR');
            }
        } catch (\Throwable $exception) {
            Storage::delete($filePath);
            throw $exception;
        }

        return $file;
    }

    public function getDownload(int $id, ?int $enforceStudentId = null): array
    {
        $file = File::findById($id);
        if (!$file) {
            throw new \App\Core\ApiException('فایل یافت نشد', 404, 'NOT_FOUND');
        }
        if ($enforceStudentId !== null && (int) $file['owner_id'] !== $enforceStudentId) {
            throw new \App\Core\ApiException('دسترسی غیرمجاز', 403, 'FORBIDDEN');
        }

        $path = Storage::resolve((string) $file['file_path']);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        return ['path' => $path, 'mime_type' => $mime, 'file' => $file];
    }

    public function deleteFile(int $id, ?int $enforceStudentId = null): void
    {
        $file = File::findById($id);
        if (!$file) {
            throw new \App\Core\ApiException('فایل یافت نشد', 404, 'NOT_FOUND');
        }

        if ($enforceStudentId !== null && (int) $file['owner_id'] !== $enforceStudentId) {
            throw new \App\Core\ApiException('دسترسی غیرمجاز', 403, 'FORBIDDEN');
        }

        Storage::delete($file['file_path']);
        File::delete($id);
    }

    private function normalizeDate(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return ($parsed !== false && $parsed->format('Y-m-d') === $date) ? $date : null;
    }
}
