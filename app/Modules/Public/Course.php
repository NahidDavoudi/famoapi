<?php

namespace App\Modules\Public;

use App\Core\Database;

class Course
{
    public static function findAllPublished(): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT * FROM courses ORDER BY display_order ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM courses WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function getFeatures(int $courseId): array
    {
        $db = Database::getConnection();
        try {
            $stmt = $db->prepare(
                'SELECT * FROM course_features WHERE course_id = ? ORDER BY display_order ASC'
            );
            $stmt->execute([$courseId]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }
}