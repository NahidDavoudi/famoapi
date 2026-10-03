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

    public static function findActiveByChatId(string $chatId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT u.id, u.role,
                    COALESCE(NULLIF(u.full_name, ''), s.name, sp.name) AS full_name,
                    u.username, u.chat_id, u.supporter_id, u.linked_id
             FROM users u
             LEFT JOIN students s ON u.role = 'student' AND s.id = u.linked_id
             LEFT JOIN supporters sp ON u.role = 'supporter' AND sp.id = u.linked_id
             WHERE u.chat_id = :chat_id
               AND (
                   (u.role = 'student' AND EXISTS (
                       SELECT 1 FROM students s WHERE s.id = u.linked_id AND s.is_active = 1
                   ))
                   OR (u.role = 'supporter' AND EXISTS (
                       SELECT 1 FROM supporters sp WHERE sp.id = u.linked_id AND sp.is_active = 1
                   ))
                   OR u.role = 'admin'
               )
             LIMIT 1"
        );
        $stmt->execute(['chat_id' => $chatId]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public static function findAnyByChatId(string $chatId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM users WHERE chat_id = :chat_id LIMIT 1'
        );
        $stmt->execute(['chat_id' => $chatId]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public static function findActiveByUsername(string $username): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT u.id, u.role,
                    COALESCE(NULLIF(u.full_name, ''), s.name, sp.name) AS full_name,
                    u.username, u.chat_id, u.supporter_id, u.linked_id
             FROM users u
             LEFT JOIN students s ON u.role = 'student' AND s.id = u.linked_id
             LEFT JOIN supporters sp ON u.role = 'supporter' AND sp.id = u.linked_id
             WHERE u.username = :username
               AND (
                   (u.role = 'student' AND EXISTS (
                       SELECT 1 FROM students s WHERE s.id = u.linked_id AND s.is_active = 1
                   ))
                   OR (u.role = 'supporter' AND EXISTS (
                       SELECT 1 FROM supporters sp WHERE sp.id = u.linked_id AND sp.is_active = 1
                   ))
                   OR u.role = 'admin'
               )
             LIMIT 1"
        );
        $stmt->execute(['username' => $username]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public static function linkChatId(int $id, string $chatId): int
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE users SET chat_id = :chat_id WHERE id = :id'
        );
        $stmt->execute(['chat_id' => $chatId, 'id' => $id]);

        return $stmt->rowCount();
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
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
