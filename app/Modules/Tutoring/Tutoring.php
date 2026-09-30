<?php

namespace App\Modules\Tutoring;

use App\Core\ApiException;
use App\Core\Database;
use App\Modules\Bot\IranDay;
use PDO;

class Tutoring
{
    private const SESSION_COLUMNS = 's.id, s.session_date, s.classroom_id, s.student_id, s.attendance_id,
        s.entered_at, s.ended_at, s.client_entered_at, s.entry_source, s.needs_review,
        s.created_by, s.client_uuid, s.created_at, s.updated_at';

    /**
     * @return array<string, mixed>|null
     */
    public static function classroomForInstructor(int $instructorId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, name, sort_order, is_active
               FROM tutoring_classrooms
              WHERE instructor_id = :instructor_id
              LIMIT 1'
        );
        $stmt->execute(['instructor_id' => $instructorId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return [
            'id'         => (int) $row['id'],
            'name'       => $row['name'],
            'sort_order' => (int) $row['sort_order'],
            'is_active'  => (bool) $row['is_active'],
        ];
    }

    /**
     * Latest open status row for the instructor on a Tehran date.
     *
     * @return array<string, mixed>|null
     */
    public static function currentStatus(int $instructorId, string $date): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, instructor_id, status, status_date, started_at, ended_at
               FROM tutoring_teacher_status_log
              WHERE instructor_id = :instructor_id
                AND status_date = :date
                AND ended_at IS NULL
              ORDER BY id DESC
              LIMIT 1'
        );
        $stmt->execute(['instructor_id' => $instructorId, 'date' => $date]);
        $row = $stmt->fetch();

        return $row === false ? null : self::shapeStatus($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function openSession(int $instructorId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT ' . self::SESSION_COLUMNS . '
               FROM tutoring_sessions s
               JOIN tutoring_classrooms c ON c.id = s.classroom_id
              WHERE c.instructor_id = :instructor_id
                AND s.ended_at IS NULL
              ORDER BY s.id DESC
              LIMIT 1'
        );
        $stmt->execute(['instructor_id' => $instructorId]);
        $row = $stmt->fetch();

        return $row === false ? null : self::shapeSession($row);
    }

    /**
     * Close the instructor's open status row for the date and open a new one.
     *
     * @return array<string, mixed>
     */
    public static function setStatus(int $instructorId, string $status, string $date): array
    {
        $db = Database::getConnection();
        $now = IranDay::nowUtc();

        $close = $db->prepare(
            'UPDATE tutoring_teacher_status_log
                SET ended_at = :ended_at
              WHERE instructor_id = :instructor_id
                AND status_date = :date
                AND ended_at IS NULL'
        );
        $close->execute([
            'ended_at' => $now,
            'instructor_id' => $instructorId,
            'date' => $date,
        ]);

        $insert = $db->prepare(
            'INSERT INTO tutoring_teacher_status_log (instructor_id, status, status_date, started_at)
             VALUES (:instructor_id, :status, :date, :started_at)'
        );
        $insert->execute([
            'instructor_id' => $instructorId,
            'status' => $status,
            'date' => $date,
            'started_at' => $now,
        ]);

        return self::findStatusById((int) $db->lastInsertId());
    }

    /**
     * Active students matching a name/phone fragment.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function searchStudents(string $query): array
    {
        $sql = 'SELECT id, name, grade, field FROM students WHERE is_active = 1';
        $params = [];
        if ($query !== '') {
            $sql .= ' AND (name LIKE :name_q OR phone LIKE :phone_q)';
            $params['name_q'] = '%' . $query . '%';
            $params['phone_q'] = '%' . $query . '%';
        }
        $sql .= ' ORDER BY name ASC, id ASC';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        $students = [];
        foreach ($stmt->fetchAll() as $row) {
            $students[] = [
                'id'    => (int) $row['id'],
                'name'  => $row['name'],
                'grade' => $row['grade'] !== null ? (int) $row['grade'] : null,
                'field' => $row['field'],
            ];
        }

        return $students;
    }

    /**
     * Open a tutoring session. Idempotent on client_uuid; links a same-day
     * present attendance row when one exists. The open_student_key unique
     * index rejects a second open session for the same student.
     *
     * @return array<string, mixed>
     */
    public static function startSession(
        int $classroomId,
        int $studentId,
        string $date,
        string $enteredAtUtc,
        int $createdBy,
        ?string $clientUuid
    ): array {
        $db = Database::getConnection();

        if ($clientUuid !== null) {
            $existing = self::findSessionByClientUuid($clientUuid);
            if ($existing !== null) {
                return $existing;
            }
        }

        $attendanceId = self::findPresentAttendanceId($date, $studentId);
        $now = IranDay::nowUtc();

        try {
            $stmt = $db->prepare(
                'INSERT INTO tutoring_sessions
                    (session_date, classroom_id, student_id, attendance_id, entered_at,
                     entry_source, needs_review, created_by, client_uuid, created_at, updated_at)
                 VALUES
                    (:session_date, :classroom_id, :student_id, :attendance_id, :entered_at,
                     :entry_source, 0, :created_by, :client_uuid, :created_at, :updated_at)'
            );
            $stmt->bindValue(':session_date', $date);
            $stmt->bindValue(':classroom_id', $classroomId, PDO::PARAM_INT);
            $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
            $stmt->bindValue(':attendance_id', $attendanceId, $attendanceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':entered_at', $enteredAtUtc);
            $stmt->bindValue(':entry_source', 'auto');
            $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
            $stmt->bindValue(':client_uuid', $clientUuid, $clientUuid === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':created_at', $now);
            $stmt->bindValue(':updated_at', $now);
            $stmt->execute();

            return self::findSessionById((int) $db->lastInsertId());
        } catch (\PDOException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }

            if ($clientUuid !== null) {
                $existing = self::findSessionByClientUuid($clientUuid);
                if ($existing !== null) {
                    return $existing;
                }
            }

            if (str_contains($e->getMessage(), 'uq_sess_one_open_per_student')) {
                throw new ApiException('این دانش‌آموز در حال حاضر یک جلسه باز دارد', 409, 'CONFLICT');
            }

            throw $e;
        }
    }

    /**
     * Close an open session that belongs to the instructor's classroom.
     *
     * @return array<string, mixed>
     */
    public static function endSession(int $instructorId, int $sessionId, string $endedAtUtc): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'UPDATE tutoring_sessions s
               JOIN tutoring_classrooms c ON c.id = s.classroom_id
                SET s.ended_at = :ended_at, s.updated_at = :updated_at
              WHERE s.id = :id
                AND c.instructor_id = :instructor_id
                AND s.ended_at IS NULL'
        );
        $stmt->execute([
            'ended_at' => $endedAtUtc,
            'updated_at' => IranDay::nowUtc(),
            'id' => $sessionId,
            'instructor_id' => $instructorId,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new ApiException('جلسه یافت نشد', 404, 'NOT_FOUND');
        }

        return self::findSessionById($sessionId);
    }

    /**
     * Every active classroom with its teacher, current open status and open session.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function board(?string $since): array
    {
        $db = Database::getConnection();
        $today = IranDay::today();

        $sql = 'SELECT c.id AS classroom_id,
                       c.name AS classroom_name,
                       c.instructor_id AS instructor_id,
                       i.name AS teacher_name,
                       (SELECT st.status FROM tutoring_teacher_status_log st
                         WHERE st.instructor_id = c.instructor_id
                           AND st.status_date = :today_status
                           AND st.ended_at IS NULL
                         ORDER BY st.id DESC LIMIT 1) AS status,
                       (SELECT st.started_at FROM tutoring_teacher_status_log st
                         WHERE st.instructor_id = c.instructor_id
                           AND st.status_date = :today_since
                           AND st.ended_at IS NULL
                         ORDER BY st.id DESC LIMIT 1) AS status_since,
                       s.id AS session_id,
                       s.entered_at AS entered_at,
                       stu.id AS student_id,
                       stu.name AS student_name,
                       stu.grade AS student_grade,
                       stu.field AS student_field
                  FROM tutoring_classrooms c
                  JOIN instructors i ON i.id = c.instructor_id
                  LEFT JOIN tutoring_sessions s ON s.id = (
                        SELECT s2.id FROM tutoring_sessions s2
                         WHERE s2.classroom_id = c.id
                           AND s2.ended_at IS NULL
                         ORDER BY s2.id DESC LIMIT 1
                  )
                  LEFT JOIN students stu ON stu.id = s.student_id
                 WHERE c.is_active = 1
                 ORDER BY c.sort_order ASC, c.id ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute(['today_status' => $today, 'today_since' => $today]);

        $board = [];
        foreach ($stmt->fetchAll() as $row) {
            $activity = self::laterTimestamp($row['status_since'], $row['entered_at']);
            if ($since !== null && ($activity === null || $activity < $since)) {
                continue;
            }

            $board[] = [
                'classroom_id'   => (int) $row['classroom_id'],
                'classroom_name' => $row['classroom_name'],
                'instructor_id'  => (int) $row['instructor_id'],
                'teacher_name'   => $row['teacher_name'],
                'status'         => $row['status'],
                'status_since'   => $row['status_since'],
                'student'        => $row['student_id'] === null ? null : [
                    'id'    => (int) $row['student_id'],
                    'name'  => $row['student_name'],
                    'grade' => $row['student_grade'] !== null ? (int) $row['student_grade'] : null,
                    'field' => $row['student_field'],
                ],
                'entered_at'     => $row['entered_at'],
            ];
        }

        return $board;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findSessionByClientUuid(string $clientUuid): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT ' . self::SESSION_COLUMNS . ' FROM tutoring_sessions s WHERE s.client_uuid = :client_uuid LIMIT 1'
        );
        $stmt->execute(['client_uuid' => $clientUuid]);
        $row = $stmt->fetch();

        return $row === false ? null : self::shapeSession($row);
    }

    private static function findSessionById(int $id): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT ' . self::SESSION_COLUMNS . ' FROM tutoring_sessions s WHERE s.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            throw new ApiException('جلسه یافت نشد', 404, 'NOT_FOUND');
        }

        return self::shapeSession($row);
    }

    private static function findStatusById(int $id): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, instructor_id, status, status_date, started_at, ended_at
               FROM tutoring_teacher_status_log
              WHERE id = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            throw new ApiException('وضعیت یافت نشد', 404, 'NOT_FOUND');
        }

        return self::shapeStatus($row);
    }

    private static function findPresentAttendanceId(string $date, int $studentId): ?int
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT id FROM daily_attendance
              WHERE attendance_date = :date
                AND student_id = :student_id
                AND status = 'present'
                AND removed_at IS NULL
              ORDER BY id DESC
              LIMIT 1"
        );
        $stmt->execute(['date' => $date, 'student_id' => $studentId]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function shapeSession(array $row): array
    {
        return [
            'id'                => (int) $row['id'],
            'session_date'      => $row['session_date'],
            'classroom_id'      => (int) $row['classroom_id'],
            'student_id'        => (int) $row['student_id'],
            'attendance_id'     => $row['attendance_id'] !== null ? (int) $row['attendance_id'] : null,
            'entered_at'        => $row['entered_at'],
            'ended_at'          => $row['ended_at'],
            'client_entered_at' => $row['client_entered_at'],
            'entry_source'      => $row['entry_source'],
            'needs_review'      => (bool) $row['needs_review'],
            'created_by'        => $row['created_by'] !== null ? (int) $row['created_by'] : null,
            'client_uuid'       => $row['client_uuid'],
            'created_at'        => $row['created_at'],
            'updated_at'        => $row['updated_at'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function shapeStatus(array $row): array
    {
        return [
            'id'            => (int) $row['id'],
            'instructor_id' => (int) $row['instructor_id'],
            'status'        => $row['status'],
            'status_date'   => $row['status_date'],
            'started_at'    => $row['started_at'],
            'ended_at'      => $row['ended_at'],
        ];
    }

    private static function laterTimestamp(?string $a, ?string $b): ?string
    {
        if ($a === null) {
            return $b;
        }
        if ($b === null) {
            return $a;
        }

        return $a >= $b ? $a : $b;
    }
}
