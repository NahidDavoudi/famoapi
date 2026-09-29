<?php

namespace App\Modules\Public;

use App\Core\Cache;

class PublicService
{
    private const GROUP = 'public';
    private const TTL = 600;

    public function getCourses(): array
    {
        return Cache::remember(self::GROUP, 'courses', self::TTL, function () {
            $courses = Course::findAllPublished();
            foreach ($courses as &$course) {
                $features = Course::getFeatures((int) $course['id']);
                $course['features'] = array_map(fn($f) => $f['feature_text'], $features);
                $course['badge_label'] = $course['badge_label'] ?? null;
                $course['target_grades'] = $course['target_grades'] ?? null;
                $course['format'] = $course['format'] ?? null;
                $course['full_description'] = $course['full_description'] ?? null;
            }
            unset($course);
            return ['courses' => $courses];
        });
    }

    public function getInstructors(): array
    {
        return Cache::remember(self::GROUP, 'instructors', self::TTL, function () {
            $instructors = Instructor::findAllPublished();
            foreach ($instructors as &$instructor) {
                $instructor['social_links'] = Instructor::getSocialLinks((int) $instructor['id']);
            }
            unset($instructor);
            return ['instructors' => $instructors];
        });
    }

    public function getSupporters(): array
    {
        return Cache::remember(self::GROUP, 'supporters', self::TTL, function () {
            return ['supporters' => Supporter::findAllPublished()];
        });
    }
}
