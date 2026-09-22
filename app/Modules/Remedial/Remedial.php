<?php

namespace App\Modules\Remedial;

use App\Core\Database;

class Remedial
{
    public static function findSessions(): array
    {
        try {
            $stmt = Database::getConnection()->prepare(
                "SELECT * FROM remedial_sessions ORDER BY start_date DESC"
            );
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function createSession(array $data): int
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                "INSERT INTO remedial_sessions (title, start_date, end_date, created_at, updated_at)
                 VALUES (:title, :start_date, :end_date, NOW(), NOW())"
            );
            $stmt->execute([
                'title'      => $data['title'],
                'start_date' => $data['start_date'],
                'end_date'   => $data['end_date'],
            ]);
            return (int) $db->lastInsertId();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public static function findSessionById(int $id): ?array
    {
        try {
            $stmt = Database::getConnection()->prepare(
                "SELECT * FROM remedial_sessions WHERE id = :id LIMIT 1"
            );
            $stmt->execute(['id' => $id]);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getClassesByField(string $field, int $sessionId): array
    {
        try {
            $stmt = Database::getConnection()->prepare(
                "SELECT * FROM remedial_classes WHERE field = :field AND session_id = :session_id"
            );
            $stmt->execute(['field' => $field, 'session_id' => $sessionId]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function createClass(array $data): int
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                "INSERT INTO remedial_classes (session_id, field, class_name, description, created_at, updated_at)
                 VALUES (:session_id, :field, :class_name, :description, NOW(), NOW())"
            );
            $stmt->execute([
                'session_id'  => $data['session_id'],
                'field'       => $data['field'],
                'class_name'  => $data['class_name'],
                'description' => $data['description'] ?? '',
            ]);
            return (int) $db->lastInsertId();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public static function deleteClass(int $id): void
    {
        try {
            $stmt = Database::getConnection()->prepare("DELETE FROM remedial_classes WHERE id = :id");
            $stmt->execute(['id' => $id]);
        } catch (\Exception $e) {
        }
    }

    public static function toggleAttendance(int $sessionId, int $classId, int $studentId): array
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                "SELECT id FROM remedial_attendance WHERE session_id = :session_id AND class_id = :class_id AND student_id = :student_id LIMIT 1"
            );
            $stmt->execute([
                'session_id' => $sessionId,
                'class_id'   => $classId,
                'student_id' => $studentId,
            ]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $db->prepare("DELETE FROM remedial_attendance WHERE id = :id");
                $stmt->execute(['id' => $existing['id']]);
                return ['attended' => false];
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO remedial_attendance (session_id, class_id, student_id, attended, created_at)
                     VALUES (:session_id, :class_id, :student_id, 1, NOW())"
                );
                $stmt->execute([
                    'session_id' => $sessionId,
                    'class_id'   => $classId,
                    'student_id' => $studentId,
                ]);
                return ['attended' => true];
            }
        } catch (\Exception $e) {
            return ['attended' => false];
        }
    }

    public static function getAttendees(int $sessionId, int $classId): array
    {
        try {
            $stmt = Database::getConnection()->prepare(
                "SELECT s.*, ra.attended
                 FROM remedial_attendance ra
                 JOIN students s ON s.id = ra.student_id
                 WHERE ra.session_id = :session_id AND ra.class_id = :class_id"
            );
            $stmt->execute(['session_id' => $sessionId, 'class_id' => $classId]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function updateStudentTime(int $sessionId, int $studentId, string $field, string $value): void
    {
        try {
            $db = Database::getConnection();
            $check = $db->prepare(
                "SELECT id FROM session_student_times WHERE session_id = :session_id AND student_id = :student_id LIMIT 1"
            );
            $check->execute(['session_id' => $sessionId, 'student_id' => $studentId]);
            $existing = $check->fetch();

            if ($existing) {
                $allowed = ['start_time', 'end_time', 'total_minutes'];
                if (!in_array($field, $allowed)) return;
                $stmt = $db->prepare(
                    "UPDATE session_student_times SET {$field} = :value WHERE id = :id"
                );
                $stmt->execute(['value' => $value, 'id' => $existing['id']]);
            } else {
                $columns = ['session_id', 'student_id', $field];
                $placeholders = [':session_id', ':student_id', ':value'];
                $stmt = $db->prepare(
                    "INSERT INTO session_student_times (" . implode(',', $columns) . ")
                     VALUES (" . implode(',', $placeholders) . ")"
                );
                $stmt->execute([
                    'session_id' => $sessionId,
                    'student_id' => $studentId,
                    'value'      => $value,
                ]);
            }
        } catch (\Exception $e) {
        }
    }
}