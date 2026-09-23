<?php

namespace App\Modules\Public;

class PublicService
{
    public function getCourses(): array
    {
        $courses = Course::findAllPublished();
        foreach ($courses as &$course) {
            $features = Course::getFeatures((int) $course['id']);
            $course['features'] = array_map(fn($f) => $f['feature_text'], $features);
            $course['badge_label'] = $course['badge_label'] ?? null;
            $course['target_grades'] = $course['target_grades'] ?? null;
            $course['format'] = $course['format'] ?? null;
            $course['full_description'] = $course['full_description'] ?? null;
        }
        return ['courses' => $courses];
    }

    public function getInstructors(): array
    {
        $instructors = Instructor::findAllPublished();
        foreach ($instructors as &$instructor) {
            $instructor['social_links'] = Instructor::getSocialLinks((int) $instructor['id']);
        }
        return ['instructors' => $instructors];
    }

    public function getSupporters(): array
    {
        return ['supporters' => Supporter::findAllPublished()];
    }
}