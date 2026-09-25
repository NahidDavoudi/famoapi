<?php

namespace App\Modules\Public;

use App\Core\Database;

class Instructor
{
    public static function findAllPublished(): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT * FROM instructors ORDER BY display_order ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getSocialLinks(int $instructorId): array
    {
        $db = Database::getConnection();
        try {
            $stmt = $db->prepare(
                'SELECT * FROM instructor_social_links WHERE instructor_id = ?'
            );
            $stmt->execute([$instructorId]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function getSocialLinksBatch(array $instructorIds): array
    {
        if (empty($instructorIds)) {
            return [];
        }
        $db = Database::getConnection();
        try {
            $placeholders = implode(',', array_fill(0, count($instructorIds), '?'));
            $stmt = $db->prepare(
                "SELECT * FROM instructor_social_links WHERE instructor_id IN ($placeholders) ORDER BY instructor_id ASC"
            );
            $stmt->execute($instructorIds);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }
}