<?php

namespace App\Modules\Outbox;

use App\Core\Database;
use App\Modules\Bot\TelegramLink;
use App\Modules\Broadcasts\BroadcastRecipient;
use App\Modules\Students\Student;

/**
 * Module 4: unified Telegram outbox.
 *
 * Producers (thread send/reply, later broadcasts) enqueue items; the external
 * bot host claims batches and reports results. Supporter notifications are
 * de-duplicated per (supporter, student) within a configurable window.
 */
class OutboxService
{
    public function enqueueReportNotification(
        int $studentId,
        int $supporterId,
        int $messageId,
        string $day,
        ?string $body,
        array $attachments
    ): array {
        $student = Student::findById($studentId);
        $studentName = (string) ($student['name'] ?? '');
        $text = $body !== null && $body !== ''
            ? $body
            : sprintf('پیام جدید از %s', $studentName !== '' ? $studentName : 'دانش‌آموز');

        $window = (int) ($_ENV['BOT_REPORT_NOTIFY_WINDOW'] ?? 300);
        if ($window < 1) {
            $window = 300;
        }
        $dedupKey = sprintf(
            'new_report:%d:%d:%d',
            $supporterId,
            $studentId,
            intdiv(time(), $window)
        );

        return $this->enqueue(
            'student_report',
            'supporter',
            $supporterId,
            $text,
            $attachments,
            [
                'student_id'   => $studentId,
                'student_name' => $studentName,
                'supporter_id' => $supporterId,
                'day'          => $day,
                'message_id'   => $messageId,
            ],
            $dedupKey
        );
    }

    public function enqueueReplyNotification(
        int $studentId,
        int $supporterId,
        int $messageId,
        string $day,
        ?string $body,
        array $attachments
    ): array {
        return $this->enqueue(
            'supporter_reply',
            'student',
            $studentId,
            $body,
            $attachments,
            [
                'student_id'   => $studentId,
                'supporter_id' => $supporterId,
                'day'          => $day,
                'message_id'   => $messageId,
            ],
            null
        );
    }

    public function enqueueBroadcast(
        int $studentId,
        int $supporterId,
        int $messageId,
        int $broadcastId,
        int $recipientId,
        string $day,
        ?string $body,
        array $attachments
    ): array {
        return $this->enqueue(
            'broadcast',
            'student',
            $studentId,
            $body,
            $attachments,
            [
                'student_id'   => $studentId,
                'supporter_id' => $supporterId,
                'day'          => $day,
                'message_id'   => $messageId,
                'broadcast_id' => $broadcastId,
                'is_broadcast' => true,
            ],
            null,
            'broadcast_recipient',
            $recipientId
        );
    }

    /**
     * @param array<int,array> $attachments
     * @param array<string,mixed> $meta
     */
    public function enqueue(
        string $kind,
        string $recipientRole,
        int $recipientAccountId,
        ?string $text,
        array $attachments,
        array $meta,
        ?string $dedupKey,
        ?string $refType = null,
        ?int $refId = null
    ): array {
        $link = TelegramLink::findByRoleAndAccount($recipientRole, $recipientAccountId);
        if (!$link) {
            return ['enqueued' => false, 'reason' => 'recipient_not_linked'];
        }
        if ((int) $link['is_blocked'] === 1) {
            return ['enqueued' => false, 'reason' => 'recipient_blocked'];
        }

        $payload = json_encode([
            'kind'        => $kind,
            'text'        => $text,
            'attachments' => $this->attachmentRefs($attachments),
            'meta'        => $meta,
        ], JSON_UNESCAPED_UNICODE);

        $maxAttempts = (int) ($_ENV['BOT_OUTBOX_MAX_ATTEMPTS'] ?? 3);
        if ($maxAttempts < 1) {
            $maxAttempts = 3;
        }

        $id = OutboxItem::insert([
            'kind'                 => $kind,
            'recipient_role'       => $recipientRole,
            'recipient_account_id' => $recipientAccountId,
            'telegram_user_id'     => (int) $link['telegram_user_id'],
            'chat_id'              => (int) $link['chat_id'],
            'status'               => 'pending',
            'payload_json'         => $payload,
            'ref_type'             => $refType,
            'ref_id'               => $refId,
            'dedup_key'            => $dedupKey,
            'max_attempts'         => $maxAttempts,
        ]);

        if ($id === 0) {
            return ['enqueued' => false, 'reason' => 'deduplicated'];
        }

        return ['enqueued' => true, 'id' => $id];
    }

    /**
     * Claim a batch of pending items for a worker.
     *
     * @return array{worker_id:string,items:array<int,array>}
     */
    public function claim(int $limit, ?string $workerId): array
    {
        $limit = max(1, min(50, $limit));
        $workerId = $workerId !== null && $workerId !== ''
            ? $workerId
            : 'w_' . bin2hex(random_bytes(8));

        $ttl = (int) ($_ENV['BOT_OUTBOX_LOCK_TTL'] ?? 300);
        if ($ttl < 1) {
            $ttl = 300;
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            OutboxItem::releaseStale($ttl);
            $ids = OutboxItem::pendingIds($limit);
            if ($ids !== []) {
                OutboxItem::markProcessing($ids, $workerId);
            }
            $rows = $ids !== [] ? OutboxItem::findClaimed($ids, $workerId) : [];
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return [
            'worker_id' => $workerId,
            'items'     => array_map([$this, 'itemPayload'], $rows),
        ];
    }

    /**
     * Report send results for claimed items.
     *
     * @param array<int,array> $results
     */
    public function report(string $workerId, array $results): array
    {
        $backoff = (int) ($_ENV['BOT_OUTBOX_RETRY_BACKOFF'] ?? 60);
        if ($backoff < 0) {
            $backoff = 0;
        }

        $out = [];
        foreach ($results as $result) {
            $id = (int) ($result['id'] ?? 0);
            $status = (string) ($result['status'] ?? '');

            $item = OutboxItem::findById($id);
            if (!$item || $item['status'] !== 'processing' || (string) $item['locked_by'] !== $workerId) {
                $out[] = ['id' => $id, 'result' => 'ignored'];
                continue;
            }

            if ($status === 'sent') {
                $tgMessageId = isset($result['telegram_message_id']) ? (string) $result['telegram_message_id'] : null;
                OutboxItem::markSent($id, $tgMessageId);
                $this->syncRef($item, 'sent', $tgMessageId, null);
                $out[] = ['id' => $id, 'result' => 'sent'];
                continue;
            }

            if ($status === 'blocked') {
                OutboxItem::markSkipped($id, 'blocked');
                TelegramLink::setBlockedByRoleAndAccount((string) $item['recipient_role'], (int) $item['recipient_account_id'], true);
                $this->syncRef($item, 'blocked', null, 'blocked');
                $out[] = ['id' => $id, 'result' => 'blocked'];
                continue;
            }

            $error = (string) ($result['error'] ?? 'unknown_error');
            $attempts = (int) $item['attempts'];
            $maxAttempts = (int) $item['max_attempts'];

            if ($attempts >= $maxAttempts) {
                OutboxItem::markFailed($id, $error);
                $this->syncRef($item, 'failed', null, $error);
                $out[] = ['id' => $id, 'result' => 'failed', 'attempts' => $attempts];
            } else {
                $next = $backoff * $attempts;
                OutboxItem::markRetry($id, $error, $next);
                $out[] = ['id' => $id, 'result' => 'retry', 'attempts' => $attempts, 'next_attempt_in' => $next];
            }
        }

        return ['processed' => count($out), 'results' => $out];
    }

    private function syncRef(array $item, string $status, ?string $telegramMessageId, ?string $error): void
    {
        if (($item['ref_type'] ?? null) === 'broadcast_recipient' && $item['ref_id'] !== null) {
            BroadcastRecipient::updateResult((int) $item['ref_id'], $status, $telegramMessageId, $error);
        }
    }

    /**
     * @param array<int,array> $attachments
     * @return array<int,array>
     */
    private function attachmentRefs(array $attachments): array
    {
        return array_map(static fn (array $a) => [
            'kind'       => $a['kind'],
            'tg_file_id' => $a['tg_file_id'],
            'file_name'  => $a['file_name'] ?? null,
            'mime_type'  => $a['mime_type'] ?? null,
            'file_size'  => $a['file_size'] ?? null,
        ], $attachments);
    }

    private function itemPayload(array $row): array
    {
        return [
            'id'                   => (int) $row['id'],
            'kind'                 => $row['kind'],
            'recipient_role'       => $row['recipient_role'],
            'recipient_account_id' => (int) $row['recipient_account_id'],
            'telegram_user_id'     => $row['telegram_user_id'] !== null ? (int) $row['telegram_user_id'] : null,
            'chat_id'              => $row['chat_id'] !== null ? (int) $row['chat_id'] : null,
            'attempts'             => (int) $row['attempts'],
            'max_attempts'         => (int) $row['max_attempts'],
            'payload'              => json_decode((string) $row['payload_json'], true),
            'created_at'           => $row['created_at'],
        ];
    }
}
