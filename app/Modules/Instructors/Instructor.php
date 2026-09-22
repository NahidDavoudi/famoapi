<?php

namespace App\Modules\Instructors;

use App\Core\Database;

class Instructor
{
    public static function findAll(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM instructors ORDER BY display_order ASC, name ASC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countAll(): int
    {
        $stmt = Database::getConnection()->query('SELECT COUNT(*) FROM instructors');
        return (int) $stmt->fetchColumn();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM instructors WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO instructors (name, title, description, image_url, initial_letter, display_order)
             VALUES (:name, :title, :description, :image_url, :initial_letter, :display_order)'
        );
        $stmt->execute([
            'name'           => $data['name'],
            'title'          => $data['title'] ?? null,
            'description'    => $data['description'] ?? null,
            'image_url'      => $data['image_url'] ?? null,
            'initial_letter' => $data['initial_letter'],
            'display_order'  => $data['display_order'] ?? 0,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): int
    {
        $sets = [];
        $params = ['id' => $id];
        $allowed = ['name', 'title', 'description', 'image_url', 'initial_letter', 'display_order'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($sets)) return 0;

        $stmt = Database::getConnection()->prepare('UPDATE instructors SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function delete(int $id): bool
    {
        $stmt = Database::getConnection()->prepare('DELETE FROM instructors WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}