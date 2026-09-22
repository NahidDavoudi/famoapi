<?php

namespace App\Modules\Topics;

class TopicService
{
    public function getChildren(int $parentId): array
    {
        return Topic::getChildren($parentId);
    }

    public function search(string $query): array
    {
        return Topic::search($query);
    }

    public function getPath(int $topicId): array
    {
        return Topic::getPath($topicId);
    }

    public function getSubjectsForGrade(int $grade, string $field): array
    {
        return Topic::getSubjectsForGrade($grade, $field);
    }
}