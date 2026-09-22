<?php

namespace App\Modules\Files;

use App\Core\Pagination;
use App\Core\Storage;

class FileService
{
    public function list(?int $studentId, int $page, int $perPage): array
    {
        $total = File::countAll($studentId);
        $pagination = Pagination::build($page, $perPage, $total);
        $items = File::findAll($studentId, $pagination['page'], $perPage);

        return [
            'items'      => $items,
            'pagination' => $pagination,
        ];
    }

    public function upload(array $uploadedFile, int $studentId, ?string $description): array
    {
        $filePath = Storage::upload($uploadedFile, 'exams', $studentId);

        $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

        $fileId = File::create([
            'owner_type'  => 'student',
            'owner_id'    => $studentId,
            'file_type'   => $extension,
            'file_path'   => $filePath,
            'file_size'   => $uploadedFile['size'],
            'description' => $description,
        ]);

        $file = File::findById($fileId);
        if (!$file) {
            throw new \RuntimeException('خطا در ذخیره اطلاعات فایل', 500);
        }

        return $file;
    }

    public function deleteFile(int $id): void
    {
        $file = File::findById($id);
        if (!$file) {
            throw new \RuntimeException('فایل یافت نشد', 404);
        }

        Storage::delete($file['file_path']);
        File::delete($id);
    }
}