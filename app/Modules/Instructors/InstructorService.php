<?php

namespace App\Modules\Instructors;

use App\Core\Pagination;
use App\Core\Storage;

class InstructorService
{
    public function list(int $page, int $perPage): array
    {
        $total = Instructor::countAll();
        $pagination = Pagination::build($page, $perPage, $total);

        $items = Instructor::findAll($pagination['page'], $pagination['per_page']);

        return [
            'items'      => $items,
            'pagination' => $pagination,
        ];
    }

    public function create(array $data): array
    {
        $data['initial_letter'] = mb_substr($data['name'], 0, 1);

        $path = null;
        if (!empty($data['image'])) {
            $path = Storage::upload($data['image'], 'instructors');
            $data['image_url'] = $path;
            unset($data['image']);
        }

        try {
            $instructorId = Instructor::create($data);
        } catch (\Throwable $e) {
            if ($path !== null) {
                Storage::delete($path);
            }
            throw $e;
        }

        return $this->get($instructorId);
    }

    public function get(int $id): array
    {
        $instructor = Instructor::findById($id);
        if (!$instructor) {
            throw new \App\Core\ApiException('استاد یافت نشد', 404, 'NOT_FOUND');
        }
        return $instructor;
    }

    public function update(int $id, array $data): array
    {
        $instructor = Instructor::findById($id);
        if (!$instructor) {
            throw new \App\Core\ApiException('استاد یافت نشد', 404, 'NOT_FOUND');
        }

        if (isset($data['name'])) {
            $data['initial_letter'] = mb_substr($data['name'], 0, 1);
        }

        $oldPath = $instructor['image_url'] ?? null;
        $newPath = null;
        if (!empty($data['image'])) {
            $path = Storage::upload($data['image'], 'instructors');
            $data['image_url'] = $path;
            $newPath = $path;
            unset($data['image']);
        }

        try {
            Instructor::update($id, $data);
        } catch (\Throwable $e) {
            if ($newPath !== null) {
                Storage::delete($newPath);
            }
            throw $e;
        }

        if ($newPath !== null && $oldPath) {
            Storage::delete($oldPath);
        }

        return $this->get($id);
    }

    public function delete(int $id): void
    {
        $instructor = Instructor::findById($id);
        if (!$instructor) {
            throw new \App\Core\ApiException('استاد یافت نشد', 404, 'NOT_FOUND');
        }

        if (!empty($instructor['image_url'])) {
            Storage::delete($instructor['image_url']);
        }

        Instructor::delete($id);
    }
}
