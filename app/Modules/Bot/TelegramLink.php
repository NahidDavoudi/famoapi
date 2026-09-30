<?php

namespace App\Modules\Bot;

use App\Core\Database;

/**
 * Model for the `telegram_links` table: links a Telegram user/chat to exactly
 * one account for a given role. Legacy chat_id columns and bot_* tables are
 * intentionally not used.
 */
class TelegramLink
{
    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM telegram_links WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findByTelegramUserAndRole(int $telegramUserId, string $role): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM telegram_links WHERE telegram_user_id = :tg AND role = :role LIMIT 1'
        );
        $stmt->execute(['tg' => $telegramUserId, 'role' => $role]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @return array<int,array>
     */
    public static function findByTelegramUser(int $telegramUserId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM telegram_links WHERE telegram_user_id = :tg ORDER BY role ASC'
        );
        $stmt->execute(['tg' => $telegramUserId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<int,array>
     */
    public static function findByChatId(int $chatId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM telegram_links WHERE chat_id = :chat ORDER BY role ASC'
        );
        $stmt->execute(['chat' => $chatId]);

        return $stmt->fetchAll();
    }

    public static function findByRoleAndAccount(string $role, int $accountId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM telegram_links WHERE role = :role AND account_id = :account LIMIT 1'
        );
        $stmt->execute(['role' => $role, 'account' => $accountId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(int $telegramUserId, int $chatId, string $role, int $accountId): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO telegram_links (telegram_user_id, chat_id, role, account_id, is_blocked, linked_at, updated_at)
             VALUES (:tg, :chat, :role, :account, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $stmt->execute([
            'tg'      => $telegramUserId,
            'chat'    => $chatId,
            'role'    => $role,
            'account' => $accountId,
        ]);

        return (int) $db->lastInsertId();
    }

    public static function deleteById(int $id): bool
    {
        $stmt = Database::getConnection()->prepare('DELETE FROM telegram_links WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }

    public static function deleteByTelegramUserAndRole(int $telegramUserId, string $role): int
    {
        $stmt = Database::getConnection()->prepare(
            'DELETE FROM telegram_links WHERE telegram_user_id = :tg AND role = :role'
        );
        $stmt->execute(['tg' => $telegramUserId, 'role' => $role]);

        return $stmt->rowCount();
    }

    public static function setBlocked(int $id, bool $blocked): int
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE telegram_links SET is_blocked = :blocked, updated_at = UTC_TIMESTAMP() WHERE id = :id'
        );
        $stmt->execute(['blocked' => $blocked ? 1 : 0, 'id' => $id]);

        return $stmt->rowCount();
    }

    public static function setBlockedByTelegramUser(int $telegramUserId, bool $blocked, ?string $role = null): int
    {
        $sql = 'UPDATE telegram_links SET is_blocked = :blocked, updated_at = UTC_TIMESTAMP()
                WHERE telegram_user_id = :tg';
        $params = ['blocked' => $blocked ? 1 : 0, 'tg' => $telegramUserId];
        if ($role !== null) {
            $sql .= ' AND role = :role';
            $params['role'] = $role;
        }

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public static function setBlockedByRoleAndAccount(string $role, int $accountId, bool $blocked): int
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE telegram_links SET is_blocked = :blocked, updated_at = UTC_TIMESTAMP()
             WHERE role = :role AND account_id = :account'
        );
        $stmt->execute(['blocked' => $blocked ? 1 : 0, 'role' => $role, 'account' => $accountId]);

        return $stmt->rowCount();
    }

    public static function setBlockedByChatId(int $chatId, bool $blocked): int
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE telegram_links SET is_blocked = :blocked, updated_at = UTC_TIMESTAMP() WHERE chat_id = :chat'
        );
        $stmt->execute(['blocked' => $blocked ? 1 : 0, 'chat' => $chatId]);

        return $stmt->rowCount();
    }

    public static function updateChatId(int $id, int $chatId): int
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE telegram_links SET chat_id = :chat, updated_at = UTC_TIMESTAMP() WHERE id = :id'
        );
        $stmt->execute(['chat' => $chatId, 'id' => $id]);

        return $stmt->rowCount();
    }
}
