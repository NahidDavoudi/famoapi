<?php

namespace App\Modules\Threads;

use App\Core\Database;

/**
 * Model for `report_message_attachments`: Telegram file references only, the
 * API never stores the file itself. Multiple attachments per message support
 * Telegram media groups (albums).
 */
class MessageAttachment
{
    public const KINDS = ['photo', 'document', 'voice', 'video', 'audio'];

    /**
     * @param array<int,array> $attachments
     */
    public static function createMany(int $messageId, array $attachments): void
    {
        if ($attachments === []) {
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO report_message_attachments
                (message_id, kind, tg_file_id, file_name, mime_type, file_size, created_at)
             VALUES (:message_id, :kind, :tg_file_id, :file_name, :mime_type, :file_size, UTC_TIMESTAMP())'
        );

        foreach ($attachments as $attachment) {
            $stmt->execute([
                'message_id' => $messageId,
                'kind'       => $attachment['kind'],
                'tg_file_id' => $attachment['tg_file_id'],
                'file_name'  => $attachment['file_name'] ?? null,
                'mime_type'  => $attachment['mime_type'] ?? null,
                'file_size'  => $attachment['file_size'] ?? null,
            ]);
        }
    }

    /**
     * Attachments grouped by message id.
     *
     * @param int[] $messageIds
     * @return array<int,array<int,array>>
     */
    public static function groupedByMessageIds(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($messageIds), '?'));
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM report_message_attachments WHERE message_id IN ({$placeholders}) ORDER BY id ASC"
        );
        $stmt->execute(array_values($messageIds));

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $grouped[(int) $row['message_id']][] = $row;
        }

        return $grouped;
    }
}
