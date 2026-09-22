<?php

namespace App\Modules\WeeklyPlans;

use App\Core\Database;

class WeeklyPlan
{
    public static function findByStudent(int $studentId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM events WHERE student_id = ? ORDER BY day_index, time_index'
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }

    public static function save(int $studentId, array $items): int
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('DELETE FROM events WHERE student_id = ?');
            $stmt->execute([$studentId]);

            if (empty($items)) {
                $db->commit();
                return 0;
            }

            $insert = $db->prepare(
                'INSERT INTO events (student_id, day_index, day_name, time_index, time_label,
                 topic_id, topic_label, subject_name, title, notes, color, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );

            $count = 0;
            foreach ($items as $item) {
                $insert->execute([
                    $studentId,
                    $item['day_index'] ?? 0,
                    $item['day_name'] ?? '',
                    $item['time_index'] ?? 0,
                    $item['time_label'] ?? '',
                    $item['topic_id'] ?? null,
                    $item['topic_label'] ?? null,
                    $item['subject_name'] ?? null,
                    $item['title'] ?? null,
                    $item['notes'] ?? null,
                    $item['color'] ?? '#445d84',
                ]);
                $count++;
            }

            $db->commit();
            return $count;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function clear(int $studentId): void
    {
        $stmt = Database::getConnection()->prepare('DELETE FROM events WHERE student_id = ?');
        $stmt->execute([$studentId]);
    }
}