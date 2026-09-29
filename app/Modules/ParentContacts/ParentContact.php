<?php

namespace App\Modules\ParentContacts;

use App\Core\Database;

class ParentContact
{
    public static function findAllForStudent(int $studentId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, student_id, parent_name, relationship, phone, is_primary, created_at, updated_at
             FROM parent_contacts WHERE student_id = :student_id
             ORDER BY is_primary DESC, id ASC'
        );
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    public static function findForStudent(int $studentId, int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, student_id, parent_name, relationship, phone, is_primary, created_at, updated_at
             FROM parent_contacts WHERE student_id = :student_id AND id = :id LIMIT 1'
        );
        $stmt->execute(['student_id' => $studentId, 'id' => $id]);
        $contact = $stmt->fetch();
        return $contact ?: null;
    }

    public static function countForStudent(int $studentId): int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(*) FROM parent_contacts WHERE student_id = :student_id'
        );
        $stmt->execute(['student_id' => $studentId]);
        return (int) $stmt->fetchColumn();
    }

    public static function create(int $studentId, array $data): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO parent_contacts (student_id, parent_name, relationship, phone, is_primary)
             VALUES (:student_id, :parent_name, :relationship, :phone, :is_primary)'
        );
        $stmt->execute([
            'student_id' => $studentId,
            'parent_name' => $data['parent_name'],
            'relationship' => $data['relationship'],
            'phone' => $data['phone'],
            'is_primary' => (int) $data['is_primary'],
        ]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function clearPrimaryForStudent(int $studentId, ?int $exceptId = null): void
    {
        $sql = 'UPDATE parent_contacts SET is_primary = 0 WHERE student_id = :student_id';
        $params = ['student_id' => $studentId];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $params['except_id'] = $exceptId;
        }
        Database::getConnection()->prepare($sql)->execute($params);
    }

    public static function updateForStudent(int $studentId, int $id, array $data): int
    {
        $fields = ['parent_name', 'relationship', 'phone', 'is_primary'];
        $sets = [];
        $params = ['student_id' => $studentId, 'id' => $id];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $field === 'is_primary' ? (int) $data[$field] : $data[$field];
            }
        }
        if ($sets === []) {
            return 0;
        }

        $stmt = Database::getConnection()->prepare(
            'UPDATE parent_contacts SET ' . implode(', ', $sets) .
            ' WHERE student_id = :student_id AND id = :id'
        );
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function deleteForStudent(int $studentId, int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            'DELETE FROM parent_contacts WHERE student_id = :student_id AND id = :id'
        );
        $stmt->execute(['student_id' => $studentId, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
