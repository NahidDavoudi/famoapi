<?php

namespace App\Modules\Appointments;

use App\Core\Database;

class Appointment
{
    public static function findAll(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = Database::getConnection()->prepare(
            "SELECT a.*, s.name as student_name, s.grade, s.field, s.phone
             FROM appointments a
             JOIN students s ON a.student_id = s.id
             ORDER BY a.appointment_date DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countAll(): int
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*) FROM appointments"
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT a.*, s.name as student_name, s.grade, s.field, s.phone
             FROM appointments a
             JOIN students s ON a.student_id = s.id
             WHERE a.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function findByStudentAndDate(int $studentId, string $date): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT a.*, s.name as student_name, s.grade, s.field, s.phone
             FROM appointments a
             JOIN students s ON a.student_id = s.id
             WHERE a.student_id = :student_id AND a.appointment_date = :date
             LIMIT 1"
        );
        $stmt->execute(['student_id' => $studentId, 'date' => $date]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            "INSERT INTO appointments (student_id, appointment_date, start_time, end_time, status, type, description, created_at, updated_at)
             VALUES (:student_id, :appointment_date, :start_time, :end_time, :status, :type, :description, NOW(), NOW())"
        );
        $stmt->execute([
            'student_id'       => $data['student_id'],
            'appointment_date' => $data['appointment_date'],
            'start_time'       => $data['start_time'],
            'end_time'         => $data['end_time'],
            'status'           => $data['status'] ?? 'pending',
            'type'             => $data['type'] ?? '',
            'description'      => $data['description'] ?? '',
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): int
    {
        $sets = [];
        $params = ['id' => $id];
        $allowed = ['student_id', 'appointment_date', 'start_time', 'end_time', 'status', 'type', 'description'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($sets)) return 0;

        $sets[] = 'updated_at = NOW()';

        $stmt = Database::getConnection()->prepare(
            'UPDATE appointments SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE appointments SET status = :status, updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['id' => $id, 'status' => $status]);
    }

    public static function delete(int $id): bool
    {
        $stmt = Database::getConnection()->prepare('DELETE FROM appointments WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public static function getThisWeekForStudent(int $studentId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT a.*, s.name as student_name, s.grade, s.field, s.phone
             FROM appointments a
             JOIN students s ON a.student_id = s.id
             WHERE a.student_id = :student_id
               AND YEARWEEK(a.appointment_date, 1) = YEARWEEK(CURDATE(), 1)
             LIMIT 1"
        );
        $stmt->execute(['student_id' => $studentId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}