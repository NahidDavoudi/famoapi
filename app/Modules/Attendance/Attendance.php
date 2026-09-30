<?php

namespace App\Modules\Attendance;

use App\Core\ApiException;
use App\Core\Database;
use App\Modules\Bot\IranDay;
use PDO;

class Attendance
{
    public const TIME_COLUMNS = ['arrived_at', 'exam_started_at', 'exam_ended_at', 'departed_at'];

    /**
     * Active students for a Tehran day, each LEFT JOINed to its same-day row,
     * merged with guest rows (student_id IS NULL). Removed rows are excluded.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listDay(string $date, ?string $field): array
    {
        $db = Database::getConnection();
        $field = ($field === null || $field === '') ? null : $field;
        $entries = [];

        $sql = "SELECT s.id AS student_id, s.name AS student_name, s.field AS student_field,
                       a.id AS id, a.guest_name AS guest_name, a.status AS status,
                       a.arrived_at, a.exam_started_at, a.exam_ended_at, a.departed_at, a.created_at
                FROM students s
                LEFT JOIN daily_attendance a
                  ON a.student_id = s.id
                 AND a.attendance_date = :date
                 AND a.removed_at IS NULL
                WHERE s.is_active = 1";
        $params = ['date' => $date];
        if ($field !== null) {
            $sql .= ' AND s.field = :field';
            $params['field'] = $field;
        }
        $sql .= ' ORDER BY s.name ASC, s.id ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $entries[] = self::shapeRow($row);
        }

        $guestSql = "SELECT NULL AS student_id, NULL AS student_name, a.field AS field,
                            a.id AS id, a.guest_name AS guest_name, a.status AS status,
                            a.arrived_at, a.exam_started_at, a.exam_ended_at, a.departed_at, a.created_at
                     FROM daily_attendance a
                     WHERE a.attendance_date = :date
                       AND a.student_id IS NULL
                       AND a.removed_at IS NULL";
        $guestParams = ['date' => $date];
        if ($field !== null) {
            $guestSql .= ' AND a.field = :field';
            $guestParams['field'] = $field;
        }
        $guestSql .= ' ORDER BY a.id ASC';

        $guestStmt = $db->prepare($guestSql);
        $guestStmt->execute($guestParams);
        foreach ($guestStmt->fetchAll() as $row) {
            $entries[] = self::shapeRow($row);
        }

        return $entries;
    }

    /**
     * Upsert the (date, student) row. Idempotent on client_uuid.
     *
     * @return array<string, mixed>
     */
    public static function upsertEntry(
        string $date,
        int $studentId,
        ?string $status,
        bool $removed,
        ?string $clientUuid
    ): array {
        $db = Database::getConnection();
        $status = $status ?? 'present';
        $removedAt = $removed ? IranDay::nowUtc() : null;

        if ($clientUuid !== null) {
            $existing = self::findEntryByClientUuid($clientUuid);
            if ($existing !== null) {
                return $existing;
            }
        }

        $existingId = self::findIdByDateStudent($date, $studentId);
        if ($existingId !== null) {
            $stmt = $db->prepare(
                'UPDATE daily_attendance
                    SET status = :status, removed_at = :removed_at, updated_at = :updated_at
                  WHERE id = :id'
            );
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':removed_at', $removedAt, $removedAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':updated_at', IranDay::nowUtc());
            $stmt->bindValue(':id', $existingId, PDO::PARAM_INT);
            $stmt->execute();

            return self::findEntryById($existingId);
        }

        try {
            $now = IranDay::nowUtc();
            $stmt = $db->prepare(
                'INSERT INTO daily_attendance
                    (attendance_date, student_id, status, removed_at, client_uuid, created_at, updated_at)
                 VALUES (:date, :student_id, :status, :removed_at, :client_uuid, :created_at, :updated_at)'
            );
            $stmt->bindValue(':date', $date);
            $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':removed_at', $removedAt, $removedAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':client_uuid', $clientUuid, $clientUuid === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':created_at', $now);
            $stmt->bindValue(':updated_at', $now);
            $stmt->execute();

            return self::findEntryById((int) $db->lastInsertId());
        } catch (\PDOException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }

            if ($clientUuid !== null) {
                $existing = self::findEntryByClientUuid($clientUuid);
                if ($existing !== null) {
                    return $existing;
                }
            }

            $existingId = self::findIdByDateStudent($date, $studentId);
            if ($existingId !== null) {
                return self::findEntryById($existingId);
            }

            throw $e;
        }
    }

    /**
     * Set one timestamp column. $column must come from TIME_COLUMNS.
     *
     * @return array<string, mixed>
     */
    public static function setTime(int $id, string $column, ?string $value): array
    {
        if (!in_array($column, self::TIME_COLUMNS, true)) {
            throw new ApiException('ستون زمان نامعتبر است', 422, 'VALIDATION_ERROR');
        }

        $db = Database::getConnection();
        if (self::findIdByPk($id) === null) {
            throw new ApiException('رکورد حضور یافت نشد', 404, 'NOT_FOUND');
        }

        $stmt = $db->prepare(
            "UPDATE daily_attendance SET {$column} = :value, updated_at = :updated_at WHERE id = :id"
        );
        $stmt->bindValue(':value', $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':updated_at', IranDay::nowUtc());
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return self::findEntryById($id);
    }

    /**
     * @return array<string, mixed>
     */
    public static function addGuest(string $date, string $guestName, string $field): array
    {
        $db = Database::getConnection();
        $now = IranDay::nowUtc();

        $stmt = $db->prepare(
            'INSERT INTO daily_attendance
                (attendance_date, student_id, guest_name, field, status, created_at, updated_at)
             VALUES (:date, NULL, :guest_name, :field, :status, :created_at, :updated_at)'
        );
        $stmt->bindValue(':date', $date);
        $stmt->bindValue(':guest_name', $guestName);
        $stmt->bindValue(':field', $field);
        $stmt->bindValue(':status', 'present');
        $stmt->bindValue(':created_at', $now);
        $stmt->bindValue(':updated_at', $now);
        $stmt->execute();

        return self::findEntryById((int) $db->lastInsertId());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findEntryById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT a.id AS id, a.student_id AS student_id, a.guest_name AS guest_name,
                    a.field AS field, a.status AS status,
                    a.arrived_at, a.exam_started_at, a.exam_ended_at, a.departed_at, a.created_at,
                    s.name AS student_name, s.field AS student_field
             FROM daily_attendance a
             LEFT JOIN students s ON s.id = a.student_id
             WHERE a.id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? self::shapeRow($row) : null;
    }

    private static function findEntryByClientUuid(string $clientUuid): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id FROM daily_attendance WHERE client_uuid = :uuid LIMIT 1'
        );
        $stmt->execute(['uuid' => $clientUuid]);
        $id = $stmt->fetchColumn();

        return $id !== false ? self::findEntryById((int) $id) : null;
    }

    private static function findIdByDateStudent(string $date, int $studentId): ?int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id FROM daily_attendance WHERE attendance_date = :date AND student_id = :student_id LIMIT 1'
        );
        $stmt->execute(['date' => $date, 'student_id' => $studentId]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private static function findIdByPk(int $id): ?int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id FROM daily_attendance WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $found = $stmt->fetchColumn();

        return $found !== false ? (int) $found : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function shapeRow(array $row): array
    {
        $isGuest = $row['student_id'] === null;

        return [
            'id'              => $row['id'] !== null ? (int) $row['id'] : null,
            'student_id'      => $isGuest ? null : (int) $row['student_id'],
            'name'            => $isGuest ? null : $row['student_name'],
            'guest_name'      => $isGuest ? $row['guest_name'] : null,
            'is_guest'        => $isGuest,
            'field'           => $isGuest ? $row['field'] : $row['student_field'],
            'status'          => $row['status'],
            'arrived_at'      => $row['arrived_at'],
            'exam_started_at' => $row['exam_started_at'],
            'exam_ended_at'   => $row['exam_ended_at'],
            'departed_at'     => $row['departed_at'],
            'created_at'      => $row['created_at'],
        ];
    }
}
