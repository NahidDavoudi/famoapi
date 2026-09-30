<?php

namespace App\Modules\Broadcasts;

use App\Core\Database;

/**
 * Model for `report_broadcast_recipients`: per-recipient delivery result of a
 * broadcast, kept in sync with the outbox delivery report.
 */
class BroadcastRecipient
{
    public static function create(
        int $broadcastId,
        int $studentId,
        ?int $messageId,
        ?int $outboxId,
        string $status,
        ?string $error
    ): int {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO report_broadcast_recipients
                (broadcast_id, student_id, message_id, outbox_id, status, error, created_at, updated_at)
             VALUES (:broadcast_id, :student_id, :message_id, :outbox_id, :status, :error, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $stmt->execute([
            'broadcast_id' => $broadcastId,
            'student_id'   => $studentId,
            'message_id'   => $messageId,
            'outbox_id'    => $outboxId,
            'status'       => $status,
            'error'        => $error,
        ]);

        return (int) $db->lastInsertId();
    }

    public static function setOutboxId(int $id, int $outboxId): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE report_broadcast_recipients SET outbox_id = :outbox_id, updated_at = UTC_TIMESTAMP() WHERE id = :id'
        );
        $stmt->execute(['outbox_id' => $outboxId, 'id' => $id]);
    }

    public static function updateResult(int $id, string $status, ?string $telegramMessageId, ?string $error): int
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE report_broadcast_recipients
             SET status = :status, telegram_message_id = :tg_message_id, error = :error, updated_at = UTC_TIMESTAMP()
             WHERE id = :id'
        );
        $stmt->execute([
            'status'          => $status,
            'tg_message_id'   => $telegramMessageId,
            'error'           => $error !== null ? mb_substr($error, 0, 500) : null,
            'id'              => $id,
        ]);

        return $stmt->rowCount();
    }

    /**
     * @return array<int,array>
     */
    public static function findByBroadcast(int $broadcastId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT r.*, s.name AS student_name
             FROM report_broadcast_recipients r
             LEFT JOIN students s ON s.id = r.student_id
             WHERE r.broadcast_id = :broadcast_id
             ORDER BY r.id ASC'
        );
        $stmt->execute(['broadcast_id' => $broadcastId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string,int>
     */
    public static function summary(int $broadcastId): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT status, COUNT(*) AS total
             FROM report_broadcast_recipients
             WHERE broadcast_id = :broadcast_id
             GROUP BY status"
        );
        $stmt->execute(['broadcast_id' => $broadcastId]);

        $summary = ['pending' => 0, 'sent' => 0, 'failed' => 0, 'blocked' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $summary[$row['status']] = (int) $row['total'];
        }

        return $summary;
    }
}
