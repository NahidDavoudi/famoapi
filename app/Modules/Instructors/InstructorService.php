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

        $items = Instructor::findAll($pagination['page'], $perPage);

        return [
            'items'      => $items,
            'pagination' => $pagination,
        ];
    }

    public function create(array $data): array
    {
        $data['initial_letter'] = mb_substr($data['name'], 0, 1);

        $instructorId = Instructor::create($data);

        if (!empty($data['image'])) {
            $path = Storage::upload($data['image'], 'instructors', $instructorId);
            Instructor::update($instructorId, ['image_url' => $path]);
        }

        return $this->get($instructorId);
    }

    public function get(int $id): array
    {
        $instructor = Instructor::findById($id);
        if (!$instructor) {
            throw new \RuntimeException('استاد یافت نشد', 404);
        }
        return $instructor;
    }

    public function update(int $id, array $data): array
    {
        $instructor = Instructor::findById($id);
        if (!$instructor) {
            throw new \RuntimeException('استاد یافت نشد', 404);
        }

        if (isset($data['name'])) {
            $data['initial_letter'] = mb_substr($data['name'], 0, 1);
        }

        if (!empty($data['image'])) {
            if (!empty($instructor['image_url'])) {
                Storage::delete($instructor['image_url']);
            }
            $path = Storage::upload($data['image'], 'instructors', $id);
            $data['image_url'] = $path;
            unset($data['image']);
        }

        Instructor::update($id, $data);

        return $this->get($id);
    }

    public function delete(int $id): void
    {
        $instructor = Instructor::findById($id);
        if (!$instructor) {
            throw new \RuntimeException('استاد یافت نشد', 404);
        }

        if (!empty($instructor['image_url'])) {
            Storage::delete($instructor['image_url']);
        }

        Instructor::delete($id);
    }
}