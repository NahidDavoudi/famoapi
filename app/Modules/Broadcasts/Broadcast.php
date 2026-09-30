<?php

namespace App\Modules\Broadcasts;

use App\Core\Database;

/**
 * Model for `report_broadcasts`: a supporter broadcast campaign. The recipient
 * list is frozen at confirm time (one row per recipient).
 */
class Broadcast
{
    public const AUDIENCES = ['no_report_today', 'all_students'];

    public static function create(
        int $supporterId,
        string $audience,
        string $day,
        ?string $body,
        int $attachmentCount,
        int $recipientCount
    ): int {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO report_broadcasts
                (supporter_id, audience, day, body, attachment_count, recipient_count, created_at)
             VALUES (:supporter_id, :audience, :day, :body, :attachment_count, :recipient_count, UTC_TIMESTAMP())'
        );
        $stmt->execute([
            'supporter_id'     => $supporterId,
            'audience'         => $audience,
            'day'              => $day,
            'body'             => $body,
            'attachment_count' => $attachmentCount,
            'recipient_count'  => $recipientCount,
        ]);

        return (int) $db->lastInsertId();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM report_broadcasts WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @return array<int,array>
     */
    public static function listBySupporter(int $supporterId, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM report_broadcasts
             WHERE supporter_id = :supporter_id
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':supporter_id', $supporterId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function countBySupporter(int $supporterId): int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(*) FROM report_broadcasts WHERE supporter_id = :supporter_id'
        );
        $stmt->execute(['supporter_id' => $supporterId]);

        return (int) $stmt->fetchColumn();
    }

    public static function countRecipientsBetween(int $supporterId, string $utcStart, string $utcEnd): int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(*)
             FROM report_broadcast_recipients r
             JOIN report_broadcasts b ON b.id = r.broadcast_id
             WHERE b.supporter_id = :supporter_id AND b.created_at >= :utc_start AND b.created_at < :utc_end'
        );
        $stmt->execute(['supporter_id' => $supporterId, 'utc_start' => $utcStart, 'utc_end' => $utcEnd]);

        return (int) $stmt->fetchColumn();
    }
}
