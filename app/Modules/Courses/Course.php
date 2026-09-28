<?php

namespace App\Modules\Courses;

use App\Core\Database;

class Course
{
    public static function findAll(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM courses ORDER BY display_order ASC, created_at DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countAll(): int
    {
        $stmt = Database::getConnection()->query('SELECT COUNT(*) FROM courses');
        return (int) $stmt->fetchColumn();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM courses WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO courses (name, icon, gradient_color_from, gradient_color_to, background_image_url, description, price, display_order)
             VALUES (:name, :icon, :gradient_color_from, :gradient_color_to, :background_image_url, :description, :price, :display_order)'
        );
        $stmt->execute([
            'name'                => $data['name'],
            'icon'                => $data['icon'] ?? null,
            'gradient_color_from' => $data['gradient_color_from'] ?? null,
            'gradient_color_to'   => $data['gradient_color_to'] ?? null,
            'background_image_url'=> $data['background_image_url'] ?? null,
            'description'         => $data['description'] ?? null,
            'price'               => $data['price'] ?? 0,
            'display_order'       => $data['display_order'] ?? 0,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): int
    {
        $sets = [];
        $params = ['id' => $id];
        $allowed = ['name', 'icon', 'gradient_color_from', 'gradient_color_to', 'background_image_url', 'description', 'price', 'display_order'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($sets)) return 0;

        $stmt = Database::getConnection()->prepare('UPDATE courses SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function delete(int $id): bool
    {
        $stmt = Database::getConnection()->prepare('DELETE FROM courses WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public static function getList(): array
    {
        $stmt = Database::getConnection()->prepare('SELECT id, name FROM courses ORDER BY display_order ASC, name ASC');
        $stmt->execute();
        return $stmt->fetchAll();
    }
}