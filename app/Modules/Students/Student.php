<?php

namespace App\Modules\Students;

use App\Core\Database;

class Student
{
    public static function findAll(array $filters, int $page, int $perPage): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(s.name LIKE :search OR s.phone LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['field'])) {
            $conditions[] = 's.field = :field';
            $params['field'] = $filters['field'];
        }
        if (!empty($filters['grade'])) {
            $conditions[] = 's.grade = :grade';
            $params['grade'] = (int) $filters['grade'];
        }

        $where = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $offset = ($page - 1) * $perPage;

        $stmt = Database::getConnection()->prepare(
            "SELECT s.* FROM students s {$where} ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countAll(array $filters): int
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(s.name LIKE :search OR s.phone LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['field'])) {
            $conditions[] = 's.field = :field';
            $params['field'] = $filters['field'];
        }
        if (!empty($filters['grade'])) {
            $conditions[] = 's.grade = :grade';
            $params['grade'] = (int) $filters['grade'];
        }

        $where = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $stmt = Database::getConnection()->prepare("SELECT COUNT(*) FROM students s {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT s.*, u.id AS user_id, u.username, u.role
             FROM students s
             LEFT JOIN users u ON u.linked_id = s.id AND u.role = :role
             WHERE s.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'role' => 'student']);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function findByName(string $name): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM students WHERE name LIKE :name LIMIT 20'
        );
        $stmt->execute(['name' => '%' . $name . '%']);
        $result = $stmt->fetchAll();
        return $result ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO students (name, grade, field, phone, national_id, created_at, updated_at)
             VALUES (:name, :grade, :field, :phone, :national_id, NOW(), NOW())'
        );
        $stmt->execute([
            'name'        => $data['name'],
            'grade'       => $data['grade'],
            'field'       => $data['field'],
            'phone'       => $data['phone'],
            'national_id' => $data['national_id'],
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): int
    {
        $sets = [];
        $params = ['id' => $id];
        $allowed = ['name', 'grade', 'field', 'phone', 'national_id'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($sets)) return 0;

        $sets[] = 'updated_at = NOW()';

        $stmt = Database::getConnection()->prepare(
            'UPDATE students SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM users WHERE linked_id = :linked_id AND role = :role');
        $stmt->execute(['linked_id' => $id, 'role' => 'student']);

        $stmt = $db->prepare('DELETE FROM students WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public static function getList(): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, name FROM students ORDER BY name ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}