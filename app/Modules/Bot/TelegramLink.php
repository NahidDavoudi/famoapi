<?php

namespace App\Modules\Bot;

use App\Core\Database;

class TelegramLink
{
    /** همه اتصال‌های یک چت (چند نقش ممکنه باشه). */
    public static function findByChatId(int $chatId): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT u.id,
                    u.username,
                    u.role,
                    u.linked_id,
                    u.linked_id AS account_id,
                    u.chat_id,
                    COALESCE(u.full_name, s.name) AS full_name,
                    COALESCE(u.full_name, s.name) AS name
             FROM users u
             LEFT JOIN students s ON u.role = 'student' AND s.id = u.linked_id
             WHERE u.chat_id = :chat
             ORDER BY u.role ASC"
        );
        $stmt->execute(['chat' => (string) $chatId]);

        return $stmt->fetchAll();
    }

    /** یک اتصال مشخص با chat_id + role. */
    public static function findByChatIdAndRole(int $chatId, string $role): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT u.id,
                    u.username,
                    u.role,
                    u.linked_id,
                    u.linked_id AS account_id,
                    u.chat_id,
                    COALESCE(u.full_name, s.name) AS full_name,
                    COALESCE(u.full_name, s.name) AS name
             FROM users u
             LEFT JOIN students s ON u.role = 'student' AND s.id = u.linked_id
             WHERE u.chat_id = :chat AND u.role = :role
             LIMIT 1"
        );
        $stmt->execute(['chat' => (string) $chatId, 'role' => $role]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** chat_id را روی کاربر موجود ست می‌کند. */
    public static function attachChatId(int $userId, int $chatId): int
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE users SET chat_id = :chat WHERE id = :id'
        );
        $stmt->execute(['chat' => (string) $chatId, 'id' => $userId]);

        return $stmt->rowCount();
    }
}