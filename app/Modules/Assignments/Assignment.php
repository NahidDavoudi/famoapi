<?php

namespace App\Modules\Assignments;

use App\Core\Database;

/**
 * Model for `student_supporter_assignments`, the single canonical source of
 * student→supporter assignments. History is preserved: reassignment
 * deactivates the previous row instead of deleting it.
 */
class Assignment
{
    public static function findActiveByStudent(int $studentId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT a.*, sp.name AS supporter_name
             FROM student_supporter_assignments a
             LEFT JOIN supporters sp ON sp.id = a.supporter_id
             WHERE a.student_id = :student_id AND a.is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['student_id' => $studentId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function lockActiveByStudent(int $studentId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM student_supporter_assignments
             WHERE student_id = :student_id AND is_active = 1
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['student_id' => $studentId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function deactivateActive(int $studentId): int
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE student_supporter_assignments
             SET is_active = 0, deactivated_at = UTC_TIMESTAMP()
             WHERE student_id = :student_id AND is_active = 1'
        );
        $stmt->execute(['student_id' => $studentId]);

        return $stmt->rowCount();
    }

    public static function insert(int $studentId, int $supporterId, ?int $assignedBy, ?string $note): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO student_supporter_assignments
                (student_id, supporter_id, is_active, assigned_by, note, assigned_at)
             VALUES (:student_id, :supporter_id, 1, :assigned_by, :note, UTC_TIMESTAMP())'
        );
        $stmt->execute([
            'student_id'   => $studentId,
            'supporter_id' => $supporterId,
            'assigned_by'  => $assignedBy,
            'note'         => $note,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * @return array<int,array>
     */
    public static function historyByStudent(int $studentId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT a.*, sp.name AS supporter_name
             FROM student_supporter_assignments a
             LEFT JOIN supporters sp ON sp.id = a.supporter_id
             WHERE a.student_id = :student_id
             ORDER BY a.assigned_at DESC, a.id DESC'
        );
        $stmt->execute(['student_id' => $studentId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<int,array>
     */
    public static function activeStudentsForSupporter(int $supporterId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT s.id, s.name, s.grade, s.field, s.phone, s.is_active,
                    a.assigned_at
             FROM student_supporter_assignments a
             JOIN students s ON s.id = a.student_id
             WHERE a.supporter_id = :supporter_id AND a.is_active = 1
             ORDER BY s.name ASC'
        );
        $stmt->execute(['supporter_id' => $supporterId]);

        return $stmt->fetchAll();
    }

    /**
     * Active students considered by the initial-fill action.
     *
     * @return array<int,array>
     */
    public static function studentsForInitialFill(bool $onlyUnassigned, array $studentIds = []): array
    {
        $sql = 'SELECT s.id, s.name, s.grade, s.field
                FROM students s
                WHERE s.is_active = 1';
        $params = [];

        if ($onlyUnassigned) {
            $sql .= ' AND NOT EXISTS (
                SELECT 1 FROM student_supporter_assignments a
                WHERE a.student_id = s.id AND a.is_active = 1
            )';
        }

        $studentIds = array_values(array_filter(array_map('intval', $studentIds), static fn ($id) => $id > 0));
        if ($studentIds !== []) {
            $sql .= ' AND s.id IN (' . implode(', ', array_fill(0, count($studentIds), '?')) . ')';
            $params = $studentIds;
        }

        $sql .= ' ORDER BY s.id ASC';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function listStudents(array $filters, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $where = self::buildWhere($filters);

        $stmt = Database::getConnection()->prepare(
            'SELECT s.id, s.name, s.grade, s.field, s.phone, s.is_active,
                    a.supporter_id, sp.name AS supporter_name, a.assigned_at
             FROM students s
             LEFT JOIN student_supporter_assignments a ON a.student_id = s.id AND a.is_active = 1
             LEFT JOIN supporters sp ON sp.id = a.supporter_id
             ' . $where['sql'] . '
             ORDER BY s.name ASC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($where['params'] as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function countStudents(array $filters): int
    {
        $where = self::buildWhere($filters);
        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(*)
             FROM students s
             LEFT JOIN student_supporter_assignments a ON a.student_id = s.id AND a.is_active = 1
             ' . $where['sql']
        );
        $stmt->execute($where['params']);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{sql:string,params:array<string,mixed>}
     */
    private static function buildWhere(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (empty($filters['include_inactive'])) {
            $conditions[] = 's.is_active = 1';
        }

        $status = $filters['status'] ?? '';
        if ($status === 'assigned') {
            $conditions[] = 'a.id IS NOT NULL';
        } elseif ($status === 'unassigned') {
            $conditions[] = 'a.id IS NULL';
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(s.name LIKE :search_name OR s.phone LIKE :search_phone)';
            $params['search_name'] = '%' . $filters['search'] . '%';
            $params['search_phone'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['field'])) {
            $conditions[] = 's.field = :field';
            $params['field'] = $filters['field'];
        }
        if (!empty($filters['grade'])) {
            $conditions[] = 's.grade = :grade';
            $params['grade'] = (int) $filters['grade'];
        }
        if (!empty($filters['supporter_id'])) {
            $conditions[] = 'a.supporter_id = :supporter_id';
            $params['supporter_id'] = (int) $filters['supporter_id'];
        }

        $sql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return ['sql' => $sql, 'params' => $params];
    }
}
