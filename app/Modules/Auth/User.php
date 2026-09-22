<?php

namespace App\Modules\Auth;

use App\Core\Database;

class User
{
    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM users WHERE username = :username LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function findByLinkedId(int $linkedId, string $role): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM users WHERE linked_id = :linked_id AND role = :role LIMIT 1'
        );
        $stmt->execute(['linked_id' => $linkedId, 'role' => $role]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function create(array $data): int
    {
        $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        unset($data['password']);

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $db = Database::getConnection();
        $stmt = $db->prepare(
            "INSERT INTO users ({$columns}) VALUES ({$placeholders})"
        );
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): int
    {
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }

        $sets = implode(', ', array_map(fn($col) => "{$col} = :{$col}", array_keys($data)));
        $data['id'] = $id;

        $stmt = Database::getConnection()->prepare(
            "UPDATE users SET {$sets} WHERE id = :id"
        );
        $stmt->execute($data);
        return $stmt->rowCount();
    }

    public static function delete(int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            'DELETE FROM users WHERE id = :id'
        );
        return $stmt->execute(['id' => $id]);
    }
}