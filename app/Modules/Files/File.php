<?php

namespace App\Modules\Files;

use App\Core\Database;

class File
{
    public static function findAll(?int $studentId, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT f.*, s.name AS student_name
                FROM files f
                JOIN students s ON f.owner_id = s.id
                WHERE f.owner_type = :owner_type
                  AND (:studentId IS NULL OR f.owner_id = :studentId2)
                ORDER BY f.created_at DESC
                LIMIT :limit OFFSET :offset';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->bindValue(':owner_type', 'student');
        $stmt->bindValue(':studentId', $studentId, \PDO::PARAM_INT);
        $stmt->bindValue(':studentId2', $studentId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countAll(?int $studentId): int
    {
        $sql = 'SELECT COUNT(*)
                FROM files f
                WHERE f.owner_type = :owner_type
                  AND (:studentId IS NULL OR f.owner_id = :studentId2)';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->bindValue(':owner_type', 'student');
        $stmt->bindValue(':studentId', $studentId, \PDO::PARAM_INT);
        $stmt->bindValue(':studentId2', $studentId, \PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT f.*, s.name AS student_name
             FROM files f
             JOIN students s ON f.owner_id = s.id
             WHERE f.id = :id AND f.owner_type = :owner_type
             LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'owner_type' => 'student']);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO files (owner_type, owner_id, file_type, file_path, file_size, description, created_at)
             VALUES (:owner_type, :owner_id, :file_type, :file_path, :file_size, :description, NOW())'
        );
        $stmt->execute([
            'owner_type'  => $data['owner_type'],
            'owner_id'    => $data['owner_id'],
            'file_type'   => $data['file_type'],
            'file_path'   => $data['file_path'],
            'file_size'   => $data['file_size'],
            'description' => $data['description'] ?? null,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function delete(int $id): bool
    {
        $stmt = Database::getConnection()->prepare('DELETE FROM files WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}