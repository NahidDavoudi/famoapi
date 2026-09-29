<?php

namespace App\Modules\Topics;

use App\Core\Cache;

class TopicService
{
    private const GROUP = 'topics';
    private const TTL = 86400;

    public function getChildren(int $parentId): array
    {
        return Cache::remember(self::GROUP, "children:{$parentId}", self::TTL, function () use ($parentId) {
            return Topic::getChildren($parentId);
        });
    }

    public function search(string $query): array
    {
        return Topic::search($query);
    }

    public function getPath(int $topicId): array
    {
        return Cache::remember(self::GROUP, "path:{$topicId}", self::TTL, function () use ($topicId) {
            return Topic::getPath($topicId);
        });
    }

    public function getSubjectsForGrade(int $grade, string $field): array
    {
        return Topic::getSubjectsForGrade($grade, $field);
    }
}
