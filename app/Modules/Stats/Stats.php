<?php

namespace App\Modules\Stats;

use App\Core\Database;

/**
 * Read-only aggregate queries for admin statistics. All time bucketing uses
 * the Tehran `day` stored on threads; timestamps are UTC.
 */
class Stats
{
    public static function activeStudentCount(): int
    {
        return (int) Database::getConnection()->query(
            'SELECT COUNT(*) FROM students WHERE is_active = 1'
        )->fetchColumn();
    }

    /**
     * @return array<string,int>
     */
    public static function activeCountsByField(): array
    {
        $stmt = Database::getConnection()->query(
            'SELECT field, COUNT(*) AS total FROM students WHERE is_active = 1 GROUP BY field'
        );

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(string) $row['field']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @return array<int,int> supporter_id => active student count
     */
    public static function assignedCountsBySupporter(): array
    {
        $stmt = Database::getConnection()->query(
            'SELECT supporter_id, COUNT(*) AS total
             FROM students
             WHERE supporter_id IS NOT NULL AND is_active = 1
             GROUP BY supporter_id'
        );

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row['supporter_id']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Distinct students who submitted a report each day.
     *
     * @return array<string,int> day => student count
     */
    public static function reportsByDay(string $from, string $to): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT t.day AS day, COUNT(DISTINCT m.student_id) AS total
             FROM report_threads t
             JOIN report_messages m ON m.thread_id = t.id AND m.sender_role = 'student'
             WHERE t.day BETWEEN :from AND :to
             GROUP BY t.day"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['day']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Distinct (student, day) reports grouped by the student's field.
     *
     * @return array<string,int>
     */
    public static function reportsByField(string $from, string $to): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT s.field AS field, COUNT(DISTINCT CONCAT(t.day, ':', m.student_id)) AS total
             FROM report_threads t
             JOIN report_messages m ON m.thread_id = t.id AND m.sender_role = 'student'
             JOIN students s ON s.id = m.student_id
             WHERE t.day BETWEEN :from AND :to
             GROUP BY s.field"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(string) $row['field']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Distinct (student, day) reports grouped by the thread's snapshot supporter.
     *
     * @return array<int,int>
     */
    public static function reportsBySupporter(string $from, string $to): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT t.supporter_id AS supporter_id, COUNT(DISTINCT CONCAT(t.day, ':', m.student_id)) AS total
             FROM report_threads t
             JOIN report_messages m ON m.thread_id = t.id AND m.sender_role = 'student'
             WHERE t.day BETWEEN :from AND :to AND t.supporter_id IS NOT NULL
             GROUP BY t.supporter_id"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row['supporter_id']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @return array<int,array>
     */
    public static function studentsWithNoReport(string $from, string $to, int $page, int $perPage, ?string $search = null): array
    {
        $offset = ($page - 1) * $perPage;
        $searchSql = $search !== null && $search !== '' ? ' AND s.name LIKE :search' : '';
        $stmt = Database::getConnection()->prepare(
            "SELECT s.id, s.name, s.grade, s.field
             FROM students s
             WHERE s.is_active = 1
               AND NOT EXISTS (
                 SELECT 1
                 FROM report_threads t
                 JOIN report_messages m ON m.thread_id = t.id AND m.sender_role = 'student'
                 WHERE t.student_id = s.id AND t.day BETWEEN :from AND :to
               ){$searchSql}
             ORDER BY s.name ASC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':from', $from);
        $stmt->bindValue(':to', $to);
        if ($searchSql !== '') {
            $stmt->bindValue(':search', '%' . $search . '%');
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function countStudentsWithNoReport(string $from, string $to, ?string $search = null): int
    {
        $searchSql = $search !== null && $search !== '' ? ' AND s.name LIKE :search' : '';
        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*)
             FROM students s
             WHERE s.is_active = 1
               AND NOT EXISTS (
                 SELECT 1
                 FROM report_threads t
                 JOIN report_messages m ON m.thread_id = t.id AND m.sender_role = 'student'
                 WHERE t.student_id = s.id AND t.day BETWEEN :from AND :to
               ){$searchSql}"
        );
        $stmt->bindValue(':from', $from);
        $stmt->bindValue(':to', $to);
        if ($searchSql !== '') {
            $stmt->bindValue(':search', '%' . $search . '%');
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Per-thread first student / first supporter timestamps for a range.
     *
     * @return array<int,array>
     */
    public static function threadFirstTimes(string $from, string $to): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT t.id AS thread_id, t.day AS day, t.supporter_id AS supporter_id,
                    MIN(CASE WHEN m.sender_role = 'student' THEN m.created_at END) AS first_student,
                    MIN(CASE WHEN m.sender_role = 'supporter' THEN m.created_at END) AS first_supporter
             FROM report_threads t
             JOIN report_messages m ON m.thread_id = t.id
             WHERE t.day BETWEEN :from AND :to AND t.supporter_id IS NOT NULL
             GROUP BY t.id, t.day, t.supporter_id"
        );
        $stmt->execute(['from' => $from, 'to' => $to]);

        return $stmt->fetchAll();
    }

    /**
     * Supporter-authored message counts by supporter.
     *
     * @return array<int,int>
     */
    public static function supporterMessageCounts(string $from, string $to): array
    {
        return self::countsBySenderRole('supporter', $from, $to);
    }

    /**
     * Broadcast message counts by the sending supporter.
     *
     * @return array<int,int>
     */
    public static function broadcastCounts(string $from, string $to): array
    {
        return self::countsBySenderRole('broadcast', $from, $to);
    }

    /**
     * @return array<int,int>
     */
    private static function countsBySenderRole(string $role, string $from, string $to): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT m.sender_account_id AS supporter_id, COUNT(*) AS total
             FROM report_messages m
             JOIN report_threads t ON t.id = m.thread_id
             WHERE m.sender_role = :role AND m.sender_account_id IS NOT NULL
               AND t.day BETWEEN :from AND :to
             GROUP BY m.sender_account_id'
        );
        $stmt->execute(['role' => $role, 'from' => $from, 'to' => $to]);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row['supporter_id']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Current unread student-message backlog per snapshot supporter.
     *
     * @return array<int,int>
     */
    public static function unreadBacklogBySupporter(): array
    {
        $stmt = Database::getConnection()->query(
            "SELECT t.supporter_id AS supporter_id, COUNT(*) AS total
             FROM report_messages m
             JOIN report_threads t ON t.id = m.thread_id
             WHERE m.sender_role = 'student' AND m.read_by_supporter = 0 AND t.supporter_id IS NOT NULL
             GROUP BY t.supporter_id"
        );

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row['supporter_id']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @param int[] $ids
     * @return array<int,string>
     */
    public static function supporterNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = Database::getConnection()->prepare(
            "SELECT id, name FROM supporters WHERE id IN ({$placeholders})"
        );
        $stmt->execute(array_values($ids));

        $names = [];
        foreach ($stmt->fetchAll() as $row) {
            $names[(int) $row['id']] = (string) $row['name'];
        }

        return $names;
    }

    /**
     * Per-day report/reply counts for one student across a range.
     *
     * @return array<int,array>
     */
    public static function studentHistory(int $studentId, string $from, string $to): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT t.day AS day,
                    SUM(CASE WHEN m.sender_role = 'student' THEN 1 ELSE 0 END) AS report_count,
                    SUM(CASE WHEN m.sender_role IN ('supporter','broadcast') THEN 1 ELSE 0 END) AS reply_count,
                    MIN(CASE WHEN m.sender_role = 'student' THEN m.created_at END) AS first_report_at,
                    MIN(CASE WHEN m.sender_role = 'supporter' THEN m.created_at END) AS first_reply_at
             FROM report_threads t
             JOIN report_messages m ON m.thread_id = t.id
             WHERE t.student_id = :student_id AND t.day BETWEEN :from AND :to
             GROUP BY t.day
             ORDER BY t.day ASC"
        );
        $stmt->execute(['student_id' => $studentId, 'from' => $from, 'to' => $to]);

        return $stmt->fetchAll();
    }
}
