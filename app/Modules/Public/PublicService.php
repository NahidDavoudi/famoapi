<?php

namespace App\Modules\Public;

class PublicService
{
    public function getCourses(): array
    {
        $courses = Course::findAllPublished();
        if (!empty($courses)) {
            $courseIds = array_column($courses, 'id');
            $features = Course::getFeaturesBatch($courseIds);
            $featuresByCourse = [];
            foreach ($features as $feature) {
                $featuresByCourse[$feature['course_id']][] = $feature['feature_text'];
            }
            foreach ($courses as &$course) {
                $course['features'] = $featuresByCourse[$course['id']] ?? [];
                $course['badge_label'] = $course['badge_label'] ?? null;
                $course['target_grades'] = $course['target_grades'] ?? null;
                $course['format'] = $course['format'] ?? null;
                $course['full_description'] = $course['full_description'] ?? null;
            }
        }
        return ['courses' => $courses];
    }

    public function getInstructors(): array
    {
        $instructors = Instructor::findAllPublished();
        if (!empty($instructors)) {
            $instructorIds = array_column($instructors, 'id');
            $socialLinks = Instructor::getSocialLinksBatch($instructorIds);
            $linksByInstructor = [];
            foreach ($socialLinks as $link) {
                $linksByInstructor[$link['instructor_id']][] = $link;
            }
            foreach ($instructors as &$instructor) {
                $instructor['social_links'] = $linksByInstructor[$instructor['id']] ?? [];
            }
        }
        return ['instructors' => $instructors];
    }

    public function getSupporters(): array
    {
        return ['supporters' => Supporter::findAllPublished()];
    }
}