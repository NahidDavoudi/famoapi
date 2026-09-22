<?php

namespace App\Modules\Public;

class PublicService
{
    public function getCourses(): array
    {
        $courses = Course::findAllPublished();
        foreach ($courses as &$course) {
            $course['features'] = Course::getFeatures((int) $course['id']);
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