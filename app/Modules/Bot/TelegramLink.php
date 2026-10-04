<?php
// در TelegramLink.php این دو متد را جایگزین findByChatId و create کن.

    /**
     * همهٔ حساب‌های وصل‌شده به این چت (نام دانش‌آموز از جدول students می‌آید).
     * @return array<int,array>
     */
    public static function findByChatId(int $chatId): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT u.username, u.role, u.linked_id,
                    COALESCE(u.full_name, s.name) AS full_name
             FROM users u
             LEFT JOIN students s ON u.role = 'student' AND s.id = u.linked_id
             WHERE u.chat_id = :chat
             ORDER BY u.role ASC"
        );
        $stmt->execute(['chat' => $chatId]);

        return $stmt->fetchAll();
    }

    /** chat_id را روی کاربر موجود ست می‌کند (به‌جای INSERT یک ردیف خالی). */
    public static function attachChatId(int $userId, int $chatId): int
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE users SET chat_id = :chat WHERE id = :id'
        );
        $stmt->execute(['chat' => $chatId, 'id' => $userId]);

        return $stmt->rowCount();
    }