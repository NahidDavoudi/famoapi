<?php

namespace App\Modules\Courses;

use App\Core\Pagination;
use App\Core\Storage;

class CourseService
{
    public function list(int $page, int $perPage): array
    {
        $total = Course::countAll();
        $pagination = Pagination::build($page, $perPage, $total);

        $items = Course::findAll($pagination['page'], $pagination['per_page']);

        return [
            'items'      => $items,
            'pagination' => $pagination,
        ];
    }

    public function create(array $data): array
    {
        $path = null;
        if (!empty($data['background_image'])) {
            $path = Storage::upload($data['background_image'], 'courses');
            $data['background_image_url'] = $path;
            unset($data['background_image']);
        }

        try {
            $courseId = Course::create($data);
        } catch (\Throwable $e) {
            if ($path !== null) {
                Storage::delete($path);
            }
            throw $e;
        }

        return $this->get($courseId);
    }

    public function get(int $id): array
    {
        $course = Course::findById($id);
        if (!$course) {
            throw new \App\Core\ApiException('دوره یافت نشد', 404, 'NOT_FOUND');
        }
        return $course;
    }

    public function update(int $id, array $data): array
    {
        $course = Course::findById($id);
        if (!$course) {
            throw new \App\Core\ApiException('دوره یافت نشد', 404, 'NOT_FOUND');
        }

        $oldPath = $course['background_image_url'] ?? null;
        $newPath = null;
        if (!empty($data['background_image'])) {
            $path = Storage::upload($data['background_image'], 'courses', $id);
            $data['background_image_url'] = $path;
            $newPath = $path;
            unset($data['background_image']);
        }

        try {
            Course::update($id, $data);
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
        $course = Course::findById($id);
        if (!$course) {
            throw new \App\Core\ApiException('دوره یافت نشد', 404, 'NOT_FOUND');
        }

        if (!empty($course['background_image_url'])) {
            Storage::delete($course['background_image_url']);
        }

        Course::delete($id);
    }
}
