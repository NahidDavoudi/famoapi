<?php

namespace App\Modules\Threads;

use App\Core\Database;


/**
 * Model for `report_threads`: exactly one thread per (student, Tehran day).
 * The thread snapshots the supporter assigned on that day.
 */
class Thread
{
    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM report_threads WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findByStudentDay(int $studentId, string $day): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM report_threads WHERE student_id = :student_id AND day = :day LIMIT 1'
        );
        $stmt->execute(['student_id' => $studentId, 'day' => $day]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Find or create the thread for a student/day and return its id.
     * The supporter snapshot is only applied when the thread is created.
     */
    public static function ensure(int $studentId, string $day, ?int $supporterId): int
    {
        $existing = self::findByStudentDay($studentId, $day);
        if ($existing) {
            return (int) $existing['id'];
        }

        $db = Database::getConnection();
        try {
            $stmt = $db->prepare(
                'INSERT INTO report_threads (student_id, day, supporter_id, created_at, updated_at)
                 VALUES (:student_id, :day, :supporter_id, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $stmt->execute([
                'student_id'   => $studentId,
                'day'          => $day,
                'supporter_id' => $supporterId,
            ]);

            return (int) $db->lastInsertId();
        } catch (\PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                $existing = self::findByStudentDay($studentId, $day);
                if ($existing) {
                    return (int) $existing['id'];
                }
            }
            throw new ApiException($e->getMessage(), 500, 'REGISTER_FAILED'); // موقتاً پیام واقعی
        }
    }

    /** Fill the snapshot supporter on an existing thread that has none. */
    public static function setSupporterIfMissing(int $threadId, int $supporterId): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE report_threads
             SET supporter_id = :supporter_id, updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND supporter_id IS NULL'
        );
        $stmt->execute(['supporter_id' => $supporterId, 'id' => $threadId]);
    }

    /**
     * Threads for a student within an inclusive day range, keyed by day.
     *
     * @return array<string,array>
     */
    public static function daysForStudent(int $studentId, string $startDay, string $endDay): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, day, supporter_id
             FROM report_threads
             WHERE student_id = :student_id AND day BETWEEN :start AND :end'
        );
        $stmt->execute(['student_id' => $studentId, 'start' => $startDay, 'end' => $endDay]);

        $byDay = [];
        foreach ($stmt->fetchAll() as $row) {
            $byDay[$row['day']] = $row;
        }

        return $byDay;
    }

    public static function existsForSupporter(int $studentId, int $supporterId): bool
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(*) FROM report_threads WHERE student_id = :student_id AND supporter_id = :supporter_id'
        );
        $stmt->execute(['student_id' => $studentId, 'supporter_id' => $supporterId]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
