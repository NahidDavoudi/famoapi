<?php

namespace App\Modules\Threads;

use App\Core\Database;

/**
 * Model for `report_messages`. Read state is tracked for the other side:
 * student messages carry read_by_supporter, supporter/broadcast messages
 * carry read_by_student.
 */
class Message
{
    public static function create(
        int $threadId,
        int $studentId,
        string $senderRole,
        ?int $senderAccountId,
        ?string $body,
        ?string $mediaGroupId,
        bool $isBroadcast,
        bool $readByStudent,
        bool $readBySupporter
    ): int {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO report_messages
                (thread_id, student_id, sender_role, sender_account_id, body, media_group_id,
                 is_broadcast, read_by_student, read_by_supporter, created_at)
             VALUES (:thread_id, :student_id, :sender_role, :sender_account_id, :body, :media_group_id,
                 :is_broadcast, :read_by_student, :read_by_supporter, UTC_TIMESTAMP())'
        );
        $stmt->execute([
            'thread_id'         => $threadId,
            'student_id'        => $studentId,
            'sender_role'       => $senderRole,
            'sender_account_id' => $senderAccountId,
            'body'              => $body,
            'media_group_id'    => $mediaGroupId,
            'is_broadcast'      => $isBroadcast ? 1 : 0,
            'read_by_student'   => $readByStudent ? 1 : 0,
            'read_by_supporter' => $readBySupporter ? 1 : 0,
        ]);

        return (int) $db->lastInsertId();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM report_messages WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @return array<int,array>
     */
    public static function findForThread(int $threadId, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM report_messages
             WHERE thread_id = :thread_id
             ORDER BY id ASC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':thread_id', $threadId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function countForThread(int $threadId): int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(*) FROM report_messages WHERE thread_id = :thread_id'
        );
        $stmt->execute(['thread_id' => $threadId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Per-day aggregates for a student within an inclusive day range.
     *
     * @return array<string,array{report_count:int,reply_count:int,reply_unread:int}>
     */
    public static function dayAggregates(int $studentId, string $startDay, string $endDay): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT t.day AS day,
                    SUM(CASE WHEN m.sender_role = 'student' THEN 1 ELSE 0 END) AS report_count,
                    SUM(CASE WHEN m.sender_role IN ('supporter','broadcast') THEN 1 ELSE 0 END) AS reply_count,
                    SUM(CASE WHEN m.sender_role IN ('supporter','broadcast') AND m.read_by_student = 0 THEN 1 ELSE 0 END) AS reply_unread
             FROM report_threads t
             LEFT JOIN report_messages m ON m.thread_id = t.id
             WHERE t.student_id = :student_id AND t.day BETWEEN :start AND :end
             GROUP BY t.day"
        );
        $stmt->execute(['student_id' => $studentId, 'start' => $startDay, 'end' => $endDay]);

        $byDay = [];
        foreach ($stmt->fetchAll() as $row) {
            $byDay[$row['day']] = [
                'report_count' => (int) $row['report_count'],
                'reply_count'  => (int) $row['reply_count'],
                'reply_unread' => (int) $row['reply_unread'],
            ];
        }

        return $byDay;
    }

    /** Most recent day that has an unread student message for the supporter. */
    public static function latestUnreadReportDay(int $studentId, int $supporterId): ?string
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT t.day
             FROM report_messages m
             JOIN report_threads t ON t.id = m.thread_id
             WHERE m.student_id = :student_id
               AND m.sender_role = 'student'
               AND m.read_by_supporter = 0
               AND (t.supporter_id = :supporter_a OR EXISTS (
                     SELECT 1 FROM student_supporter_assignments a
                     WHERE a.student_id = m.student_id AND a.supporter_id = :supporter_b AND a.is_active = 1))
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT 1"
        );
        $stmt->execute([
            'student_id'  => $studentId,
            'supporter_a' => $supporterId,
            'supporter_b' => $supporterId,
        ]);
        $day = $stmt->fetchColumn();

        return $day !== false ? (string) $day : null;
    }

    /**
     * Unread student messages for a supporter, oldest first.
     *
     * @return array<int,array>
     */
    public static function unreadForStudent(int $studentId, int $supporterId, int $limit = 200): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT m.*, t.day AS day
             FROM report_messages m
             JOIN report_threads t ON t.id = m.thread_id
             WHERE m.student_id = :student_id
               AND m.sender_role = 'student'
               AND m.read_by_supporter = 0
               AND (t.supporter_id = :supporter_a OR EXISTS (
                     SELECT 1 FROM student_supporter_assignments a
                     WHERE a.student_id = m.student_id AND a.supporter_id = :supporter_b AND a.is_active = 1))
             ORDER BY m.created_at ASC, m.id ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':student_id', $studentId, \PDO::PARAM_INT);
        $stmt->bindValue(':supporter_a', $supporterId, \PDO::PARAM_INT);
        $stmt->bindValue(':supporter_b', $supporterId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function markReadBySupporter(int $studentId, ?int $threadId = null): int
    {
        $sql = "UPDATE report_messages SET read_by_supporter = 1
                WHERE student_id = :student_id AND sender_role = 'student' AND read_by_supporter = 0";
        $params = ['student_id' => $studentId];
        if ($threadId !== null) {
            $sql .= ' AND thread_id = :thread_id';
            $params['thread_id'] = $threadId;
        }

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public static function markReadByStudent(int $studentId, ?int $threadId = null): int
    {
        $sql = "UPDATE report_messages SET read_by_student = 1
                WHERE student_id = :student_id AND sender_role IN ('supporter','broadcast') AND read_by_student = 0";
        $params = ['student_id' => $studentId];
        if ($threadId !== null) {
            $sql .= ' AND thread_id = :thread_id';
            $params['thread_id'] = $threadId;
        }

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Inbox rows: students with unread student messages for this supporter.
     *
     * @return array<int,array>
     */
    public static function unreadInbox(int $supporterId, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = Database::getConnection()->prepare(
            "SELECT m.student_id, s.name AS student_name, s.grade, s.field,
                    COUNT(*) AS unread_count, MAX(m.created_at) AS last_message_at
             FROM report_messages m
             JOIN report_threads t ON t.id = m.thread_id
             JOIN students s ON s.id = m.student_id
             WHERE m.sender_role = 'student'
               AND m.read_by_supporter = 0
               AND (t.supporter_id = :supporter_a OR EXISTS (
                     SELECT 1 FROM student_supporter_assignments a
                     WHERE a.student_id = m.student_id AND a.supporter_id = :supporter_b AND a.is_active = 1))
             GROUP BY m.student_id, s.name, s.grade, s.field
             ORDER BY last_message_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':supporter_a', $supporterId, \PDO::PARAM_INT);
        $stmt->bindValue(':supporter_b', $supporterId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function countInboxStudents(int $supporterId): int
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(DISTINCT m.student_id)
             FROM report_messages m
             JOIN report_threads t ON t.id = m.thread_id
             WHERE m.sender_role = 'student'
               AND m.read_by_supporter = 0
               AND (t.supporter_id = :supporter_a OR EXISTS (
                     SELECT 1 FROM student_supporter_assignments a
                     WHERE a.student_id = m.student_id AND a.supporter_id = :supporter_b AND a.is_active = 1))"
        );
        $stmt->execute(['supporter_a' => $supporterId, 'supporter_b' => $supporterId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Report/reply presence for students on a single day.
     *
     * @param int[] $studentIds
     * @return array<int,array{report_count:int,reply_count:int}>
     */
    public static function dayStatusForStudents(array $studentIds, string $day): array
    {
        if ($studentIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($studentIds), '?'));
        $stmt = Database::getConnection()->prepare(
            "SELECT m.student_id,
                    SUM(CASE WHEN m.sender_role = 'student' THEN 1 ELSE 0 END) AS report_count,
                    SUM(CASE WHEN m.sender_role IN ('supporter','broadcast') THEN 1 ELSE 0 END) AS reply_count
             FROM report_messages m
             JOIN report_threads t ON t.id = m.thread_id
             WHERE t.day = ? AND m.student_id IN ({$placeholders})
             GROUP BY m.student_id"
        );
        $stmt->execute(array_merge([$day], array_values($studentIds)));

        $byStudent = [];
        foreach ($stmt->fetchAll() as $row) {
            $byStudent[(int) $row['student_id']] = [
                'report_count' => (int) $row['report_count'],
                'reply_count'  => (int) $row['reply_count'],
            ];
        }

        return $byStudent;
    }

    /**
     * @param int[] $studentIds
     * @return array<int,int>
     */
    public static function unreadCountsForStudents(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($studentIds), '?'));
        $stmt = Database::getConnection()->prepare(
            "SELECT student_id, COUNT(*) AS unread_count
             FROM report_messages
             WHERE sender_role = 'student' AND read_by_supporter = 0 AND student_id IN ({$placeholders})
             GROUP BY student_id"
        );
        $stmt->execute(array_values($studentIds));

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row['student_id']] = (int) $row['unread_count'];
        }

        return $counts;
    }
}
