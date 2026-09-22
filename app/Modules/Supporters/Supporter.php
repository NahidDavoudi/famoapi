<?php

namespace App\Modules\Supporters;

use App\Core\Database;

class Supporter
{
    public static function findAll(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = Database::getConnection()->prepare(
            'SELECT s.*,
                    COALESCE(r.replied_count, 0) AS replied_count,
                    COALESCE(r.pending_count, 0) AS pending_count
             FROM supporters s
             LEFT JOIN (
                 SELECT supporter_id,
                        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS replied_count,
                        SUM(CASE WHEN status = 0 OR status IS NULL THEN 1 ELSE 0 END) AS pending_count
                 FROM reports_status
                 GROUP BY supporter_id
             ) r ON r.supporter_id = s.id
             ORDER BY s.name ASC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countAll(): int
    {
        $stmt = Database::getConnection()->query('SELECT COUNT(*) FROM supporters');
        return (int) $stmt->fetchColumn();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT s.*, u.id AS user_id, u.username, u.role
             FROM supporters s
             LEFT JOIN users u ON u.linked_id = s.id AND u.role = :role
             WHERE s.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'role' => 'supporter']);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO supporters (name, grade, field, chat_id)
             VALUES (:name, :grade, :field, :chat_id)'
        );
        $stmt->execute([
            'name'    => $data['name'],
            'grade'   => $data['grade'] ?? null,
            'field'   => $data['field'] ?? null,
            'chat_id' => $data['chat_id'] ?? null,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): int
    {
        $sets = [];
        $params = ['id' => $id];
        $allowed = ['name', 'grade', 'field', 'chat_id'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($sets)) return 0;

        $stmt = Database::getConnection()->prepare('UPDATE supporters SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM users WHERE linked_id = :linked_id AND role = :role');
        $stmt->execute(['linked_id' => $id, 'role' => 'supporter']);

        $stmt = $db->prepare('DELETE FROM supporters WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}