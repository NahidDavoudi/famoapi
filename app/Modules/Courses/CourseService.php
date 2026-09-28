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

        $items = Course::findAll($pagination['page'], $perPage);

        return [
            'items'      => $items,
            'pagination' => $pagination,
        ];
    }

    public function create(array $data): array
    {
        $courseId = Course::create($data);

        if (!empty($data['background_image'])) {
            $path = Storage::upload($data['background_image'], 'courses', $courseId);
            Course::update($courseId, ['background_image_url' => $path]);
        }

        return $this->get($courseId);
    }

    public function get(int $id): array
    {
        $course = Course::findById($id);
        if (!$course) {
            throw new \RuntimeException('دوره یافت نشد', 404);
        }
        return $course;
    }

    public function update(int $id, array $data): array
    {
        $course = Course::findById($id);
        if (!$course) {
            throw new \RuntimeException('دوره یافت نشد', 404);
        }

        if (!empty($data['background_image'])) {
            if (!empty($course['background_image_url'])) {
                Storage::delete($course['background_image_url']);
            }
            $path = Storage::upload($data['background_image'], 'courses', $id);
            $data['background_image_url'] = $path;
            unset($data['background_image']);
        }

        Course::update($id, $data);

        return $this->get($id);
    }

    public function delete(int $id): void
    {
        $course = Course::findById($id);
        if (!$course) {
            throw new \RuntimeException('دوره یافت نشد', 404);
        }

        if (!empty($course['background_image_url'])) {
            Storage::delete($course['background_image_url']);
        }

        Course::delete($id);
    }
}