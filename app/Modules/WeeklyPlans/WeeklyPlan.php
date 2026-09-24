<?php

namespace App\Modules\WeeklyPlans;

use App\Core\Database;

class WeeklyPlan
{
    private const DAY_NAMES = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

    /** Plans belonging to a student, newest first, with event counts. */
    public static function listByStudent(int $studentId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT p.id, p.student_id, p.week_date, p.weekly_notes, p.times_json,
                    p.created_at, p.updated_at,
                    COUNT(e.id) AS event_count
             FROM weekly_plans p
             LEFT JOIN events e ON e.plan_id = p.id
             WHERE p.student_id = :student_id
             GROUP BY p.id
             ORDER BY p.id DESC'
        );
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    /** A single plan joined with its student. */
    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT p.*,
                    s.name AS student_name,
                    s.grade AS student_grade,
                    s.field AS student_field,
                    s.national_id AS student_national_id
             FROM weekly_plans p
             JOIN students s ON s.id = p.student_id
             WHERE p.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** All events of a plan, with their topic chips attached. */
    public static function getEvents(int $planId): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'SELECT * FROM events WHERE plan_id = :plan_id ORDER BY day_index ASC, time_index ASC'
        );
        $stmt->execute(['plan_id' => $planId]);
        $events = $stmt->fetchAll();
        if (empty($events)) {
            return [];
        }

        $ids = array_map('intval', array_column($events, 'id'));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $topicStmt = $db->prepare(
            "SELECT * FROM event_topics WHERE event_id IN ($placeholders) ORDER BY event_id ASC, position ASC"
        );
        $topicStmt->execute($ids);

        $topicsByEvent = [];
        foreach ($topicStmt->fetchAll() as $topic) {
            $topicsByEvent[(int) $topic['event_id']][] = [
                'id'        => $topic['topic_id'],
                'label'     => $topic['topic_label'],
                'path'      => $topic['topic_path'],
                'pathShort' => $topic['topic_path_short'],
            ];
        }

        foreach ($events as &$event) {
            $event['topics'] = $topicsByEvent[(int) $event['id']] ?? [];
        }
        unset($event);

        return $events;
    }

    /** Parsed times array for a plan row. */
    public static function decodeTimes(?array $plan): array
    {
        if (!$plan || empty($plan['times_json'])) {
            return [];
        }
        $times = json_decode($plan['times_json'], true);
        return is_array($times) ? $times : [];
    }

    /**
     * Create or update a full weekly plan.
     *
     * @param array $data {
     *   plan_id?: int, student_id?: int, week_date?: string,
     *   weekly_notes?: string, times?: array, events?: array
     * }
     */
    public static function save(array $data): array
    {
        $db = Database::getConnection();
        $planId = (int) ($data['plan_id'] ?? 0);
        $studentId = (int) ($data['student_id'] ?? 0);
        $weekDate = trim((string) ($data['week_date'] ?? ''));
        $notes = $data['weekly_notes'] ?? null;
        $times = is_array($data['times'] ?? null) ? $data['times'] : [];
        $events = is_array($data['events'] ?? null) ? $data['events'] : [];

        if ($studentId <= 0) {
            throw new \RuntimeException('شناسه دانش‌آموز الزامی است', 400);
        }

        $db->beginTransaction();
        try {
            // When no plan id is given, reuse an existing plan for the same
            // student + week_date so the unique key stays valid.
            if ($planId <= 0 && $weekDate !== '') {
                $find = $db->prepare(
                    'SELECT id FROM weekly_plans WHERE student_id = :student_id AND week_date = :week_date LIMIT 1'
                );
                $find->execute(['student_id' => $studentId, 'week_date' => $weekDate]);
                $planId = (int) $find->fetchColumn();
            }

            if ($planId > 0) {
                $existing = self::findById($planId);
                if (!$existing) {
                    throw new \RuntimeException('برنامه یافت نشد', 404);
                }

                $update = $db->prepare(
                    'UPDATE weekly_plans
                     SET student_id = :student_id, week_date = :week_date,
                         weekly_notes = :weekly_notes, times_json = :times_json,
                         updated_at = NOW()
                     WHERE id = :id'
                );
                $update->execute([
                    'student_id'  => $studentId,
                    'week_date'   => $weekDate !== '' ? $weekDate : ($existing['week_date'] ?? ''),
                    'weekly_notes'=> $notes,
                    'times_json'  => json_encode($times, JSON_UNESCAPED_UNICODE),
                    'id'          => $planId,
                ]);

                $db->prepare('DELETE FROM events WHERE plan_id = :plan_id')->execute(['plan_id' => $planId]);
            } else {
                $insert = $db->prepare(
                    'INSERT INTO weekly_plans (student_id, week_date, weekly_notes, times_json, created_at, updated_at)
                     VALUES (:student_id, :week_date, :weekly_notes, :times_json, NOW(), NOW())'
                );
                $insert->execute([
                    'student_id'  => $studentId,
                    'week_date'   => $weekDate,
                    'weekly_notes'=> $notes,
                    'times_json'  => json_encode($times, JSON_UNESCAPED_UNICODE),
                ]);
                $planId = (int) $db->lastInsertId();
            }

            $count = self::insertEvents($db, $planId, $studentId, $events, $times);
            $db->commit();

            return [
                'plan_id'    => $planId,
                'student_id' => $studentId,
                'saved'      => $count,
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function insertEvents(\PDO $db, int $planId, int $studentId, array $events, array $times): int
    {
        $insert = $db->prepare(
            'INSERT INTO events (
                plan_id, student_id,
                day_index, day_name, time_index, time_label,
                topic_id, topic_path, topic_path_short, topic_label,
                subject_name, selected_grade,
                title, notes, color, created_at, updated_at
             ) VALUES (
                :plan_id, :student_id,
                :day_index, :day_name, :time_index, :time_label,
                :topic_id, :topic_path, :topic_path_short, :topic_label,
                :subject_name, :selected_grade,
                :title, :notes, :color, NOW(), NOW()
             )'
        );
        $insertTopic = $db->prepare(
            'INSERT INTO event_topics (event_id, topic_id, topic_label, topic_path, topic_path_short, position)
             VALUES (:event_id, :topic_id, :topic_label, :topic_path, :topic_path_short, :position)'
        );

        $count = 0;
        foreach ($events as $event) {
            $dayIndex = (int) ($event['day_index'] ?? $event['dayIndex'] ?? 0);
            $timeIndex = (int) ($event['time_index'] ?? $event['timeIndex'] ?? 0);
            $topics = self::normalizeTopics($event);
            $first = $topics[0] ?? null;

            $insert->execute([
                'plan_id'         => $planId,
                'student_id'      => $studentId,
                'day_index'       => $dayIndex,
                'day_name'        => self::DAY_NAMES[$dayIndex] ?? 'نامشخص',
                'time_index'      => $timeIndex,
                'time_label'      => $event['time_label'] ?? $event['timeLabel'] ?? ($times[$timeIndex] ?? null),
                'topic_id'        => $first['id'] ?? null,
                'topic_path'      => $first['path'] ?? null,
                'topic_path_short'=> $first['pathShort'] ?? null,
                'topic_label'     => $first['label'] ?? null,
                'subject_name'    => $event['subject_name'] ?? $event['subjectName'] ?? null,
                'selected_grade'  => $event['selected_grade'] ?? $event['selectedGrade'] ?? null,
                'title'           => $event['title'] ?? null,
                'notes'           => $event['notes'] ?? null,
                'color'           => $event['color'] ?? 'green',
            ]);

            $eventId = (int) $db->lastInsertId();
            $position = 0;
            foreach ($topics as $topic) {
                $insertTopic->execute([
                    'event_id'        => $eventId,
                    'topic_id'        => $topic['id'] ?? null,
                    'topic_label'     => $topic['label'] ?? null,
                    'topic_path'      => $topic['path'] ?? null,
                    'topic_path_short'=> $topic['pathShort'] ?? null,
                    'position'        => $position++,
                ]);
            }

            $count++;
        }

        return $count;
    }

    /** Normalize the topic chips of an event from either payload shape. */
    private static function normalizeTopics(array $event): array
    {
        $topics = $event['topics'] ?? null;
        if (is_array($topics) && count($topics) > 0) {
            $normalized = [];
            foreach ($topics as $topic) {
                $normalized[] = [
                    'id'        => $topic['id'] ?? $topic['topic_id'] ?? null,
                    'label'     => $topic['label'] ?? $topic['topic_label'] ?? null,
                    'path'      => $topic['path'] ?? $topic['topic_path'] ?? null,
                    'pathShort' => $topic['pathShort'] ?? $topic['path_short'] ?? $topic['topic_path_short'] ?? null,
                ];
            }
            return $normalized;
        }

        $hasLegacyTopic = !empty($event['topic_id']) || !empty($event['topicId'])
            || !empty($event['topic_label']) || !empty($event['topicLabel'])
            || !empty($event['topic_path_short']) || !empty($event['topicPathShort']);

        if ($hasLegacyTopic) {
            return [[
                'id'        => $event['topic_id'] ?? $event['topicId'] ?? null,
                'label'     => $event['topic_label'] ?? $event['topicLabel'] ?? null,
                'path'      => $event['topic_path'] ?? $event['topicPath'] ?? null,
                'pathShort' => $event['topic_path_short'] ?? $event['topicPathShort'] ?? null,
            ]];
        }

        return [];
    }

    public static function delete(int $id): bool
    {
        $stmt = Database::getConnection()->prepare('DELETE FROM weekly_plans WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public static function clearForStudent(int $studentId): int
    {
        $stmt = Database::getConnection()->prepare('DELETE FROM weekly_plans WHERE student_id = :student_id');
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->rowCount();
    }
}
