<?php

namespace App\Modules\Topics;

use App\Core\Database;

class Topic
{
    public static function getChildren(int $parentId): array
    {
        try {
            $stmt = Database::getConnection()->prepare(
                'SELECT * FROM topic_tree WHERE parent_id = :parent_id ORDER BY sort_order'
            );
            $stmt->execute(['parent_id' => $parentId]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function search(string $query): array
    {
        try {
            $stmt = Database::getConnection()->prepare(
                'SELECT * FROM topic_tree WHERE label LIKE :query ORDER BY label LIMIT 20'
            );
            $stmt->execute(['query' => '%' . $query . '%']);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function getPath(int $topicId): array
    {
        try {
            $path = [];
            $currentId = $topicId;

            while ($currentId !== null) {
                $stmt = Database::getConnection()->prepare(
                    'SELECT * FROM topic_tree WHERE id = :id LIMIT 1'
                );
                $stmt->execute(['id' => $currentId]);
                $node = $stmt->fetch();

                if (!$node) {
                    break;
                }

                array_unshift($path, $node);
                $currentId = $node['parent_id'];
            }

            return $path;
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function getSubjectsForGrade(int $grade, string $field): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT DISTINCT subject_name FROM events WHERE selected_grade = :grade ORDER BY subject_name'
        );
        $stmt->execute(['grade' => $grade]);
        return $stmt->fetchAll();
    }
}