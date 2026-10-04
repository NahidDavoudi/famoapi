<?php

namespace App\Modules\Broadcasts;

use App\Core\ApiException;
use App\Core\Database;
use App\Core\Pagination;
use App\Modules\Bot\IranDay;
use App\Modules\Outbox\OutboxService;
use App\Modules\Students\Student;
use App\Modules\Threads\Message;
use App\Modules\Threads\MessageAttachment;
use App\Modules\Threads\MessageContent;
use App\Modules\Threads\Thread;

/**
 * Module 5: supporter broadcasts to their own students only.
 *
 * Flow: preview (no writes) → confirm (freezes the recipient list, creates one
 * broadcast message per recipient inside their thread for the target day, plus
 * one outbox item per recipient). A configurable daily limit caps the number of
 * recipients a supporter can broadcast to per Iran day.
 */
class BroadcastService
{
    private OutboxService $outbox;

    public function __construct(?OutboxService $outbox = null)
    {
        $this->outbox = $outbox ?? new OutboxService();
    }

    public function preview(array $actor, array $data): array
    {
        $supporterId = $this->requireSupporter($actor);
        $audience = $this->resolveAudience($data['audience'] ?? '');
        $day = $this->resolveDay($data['day'] ?? null);

        $recipients = $this->recipients($supporterId, $audience, $day);
        $limit = $this->dailyLimit();
        $used = $this->usedToday($supporterId);

        return [
            'audience'        => $audience,
            'day'             => $day,
            'day_jalali'      => IranDay::jalali($day),
            'recipient_count' => count($recipients),
            'recipients'      => array_map(static fn (array $s) => [
                'student_id' => (int) $s['id'],
                'name'       => $s['name'],
                'grade'      => (int) $s['grade'],
                'field'      => $s['field'],
            ], $recipients),
            'daily_limit'     => $limit,
            'used_today'      => $used,
            'remaining_today' => max(0, $limit - $used),
        ];
    }

    public function confirm(array $actor, array $data): array
    {
        $supporterId = $this->requireSupporter($actor);
        $audience = $this->resolveAudience($data['audience'] ?? '');
        $day = $this->resolveDay($data['day'] ?? null);
        [$body, $attachments, $mediaGroupId] = MessageContent::parse($data);

        $recipients = $this->recipients($supporterId, $audience, $day);
        if ($recipients === []) {
            throw new ApiException('گیرنده‌ای برای این پیام گروهی یافت نشد', 422, 'NO_RECIPIENTS');
        }

        $limit = $this->dailyLimit();
        $used = $this->usedToday($supporterId);
        if ($used + count($recipients) > $limit) {
            throw new ApiException(
                'محدودیت روزانه ارسال پیام گروهی برای پشتیبان تکمیل شده است',
                429,
                'BROADCAST_DAILY_LIMIT'
            );
        }

        $enqueued = 0;
        $blocked = 0;
        $failed = 0;

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $broadcastId = Broadcast::create(
                $supporterId,
                $audience,
                $day,
                $body,
                count($attachments),
                count($recipients)
            );

            foreach ($recipients as $student) {
                $studentId = (int) $student['id'];
                $threadId = Thread::ensure($studentId, $day, $supporterId);
                $messageId = Message::create(
                    $threadId,
                    $studentId,
                    'broadcast',
                    $supporterId,
                    $body,
                    $mediaGroupId,
                    true,
                    false,
                    true
                );
                MessageAttachment::createMany($messageId, $attachments);

                $recipientId = BroadcastRecipient::create($broadcastId, $studentId, $messageId, null, 'pending', null);

                $result = $this->outbox->enqueueBroadcast(
                    $studentId,
                    $supporterId,
                    $messageId,
                    $broadcastId,
                    $recipientId,
                    $day,
                    $body,
                    $attachments
                );

                if (!empty($result['enqueued'])) {
                    BroadcastRecipient::setOutboxId($recipientId, (int) $result['id']);
                    $enqueued++;
                } elseif (($result['reason'] ?? '') === 'recipient_blocked') {
                    BroadcastRecipient::updateResult($recipientId, 'blocked', null, 'recipient_blocked');
                    $blocked++;
                } else {
                    BroadcastRecipient::updateResult($recipientId, 'failed', null, (string) ($result['reason'] ?? 'not_deliverable'));
                    $failed++;
                }
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return [
            'broadcast_id'    => $broadcastId,
            'audience'        => $audience,
            'day'             => $day,
            'day_jalali'      => IranDay::jalali($day),
            'recipient_count' => count($recipients),
            'enqueued'        => $enqueued,
            'blocked'         => $blocked,
            'failed'          => $failed,
            'summary'         => BroadcastRecipient::summary($broadcastId),
        ];
    }

    public function get(array $actor, int $broadcastId): array
    {
        $supporterId = $this->requireSupporter($actor);
        $broadcast = Broadcast::findById($broadcastId);
        if (!$broadcast || (int) $broadcast['supporter_id'] !== $supporterId) {
            throw new ApiException('پیام گروهی یافت نشد', 404, 'NOT_FOUND');
        }

        $recipients = BroadcastRecipient::findByBroadcast($broadcastId);

        return [
            'broadcast'  => $this->broadcastPayload($broadcast),
            'summary'    => BroadcastRecipient::summary($broadcastId),
            'recipients' => array_map(static fn (array $r) => [
                'student_id'          => (int) $r['student_id'],
                'student_name'        => $r['student_name'],
                'status'              => $r['status'],
                'telegram_message_id' => $r['telegram_message_id'],
                'error'               => $r['error'],
            ], $recipients),
        ];
    }

    public function list(array $actor, int $page, int $perPage): array
    {
        $supporterId = $this->requireSupporter($actor);
        $total = Broadcast::countBySupporter($supporterId);
        $pagination = Pagination::build($page, $perPage, $total);

        $items = array_map(
            fn (array $b) => $this->broadcastPayload($b),
            Broadcast::listBySupporter($supporterId, $pagination['page'], $pagination['per_page'])
        );

        return ['items' => $items, 'pagination' => $pagination];
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @return array<int,array>
     */
    private function recipients(int $supporterId, string $audience, string $day): array
    {
        $students = Student::findBySupporter($supporterId);
        if ($audience === 'all_students') {
            return $students;
        }

        $ids = array_map(static fn (array $s) => (int) $s['id'], $students);
        $status = Message::dayStatusForStudents($ids, $day);

        return array_values(array_filter(
            $students,
            static fn (array $s) => ($status[(int) $s['id']]['report_count'] ?? 0) === 0
        ));
    }

    private function requireSupporter(array $actor): int
    {
        if (($actor['role'] ?? '') !== 'supporter') {
            throw new ApiException('این عملیات فقط برای پشتیبانان مجاز است', 403, 'FORBIDDEN');
        }

        return (int) $actor['account_id'];
    }

    private function resolveAudience(string $audience): string
    {
        if (!in_array($audience, Broadcast::AUDIENCES, true)) {
            throw new ApiException('مخاطب پیام گروهی نامعتبر است', 422, 'INVALID_AUDIENCE');
        }

        return $audience;
    }

    private function resolveDay(?string $day): string
    {
        if ($day === null || $day === '') {
            return IranDay::today();
        }
        if (!IranDay::isValidDate($day)) {
            throw new ApiException('تاریخ نامعتبر است', 422, 'INVALID_DAY');
        }

        return $day;
    }

    private function dailyLimit(): int
    {
        $limit = (int) ($_ENV['BOT_BROADCAST_DAILY_LIMIT'] ?? 200);

        return $limit > 0 ? $limit : 200;
    }

    private function usedToday(int $supporterId): int
    {
        $bounds = IranDay::dayBoundsUtc(IranDay::today());

        return Broadcast::countRecipientsBetween($supporterId, $bounds['start'], $bounds['end']);
    }

    private function broadcastPayload(array $broadcast): array
    {
        $day = (string) $broadcast['day'];

        return [
            'id'               => (int) $broadcast['id'],
            'audience'         => $broadcast['audience'],
            'day'              => $day,
            'day_jalali'       => IranDay::jalali($day),
            'body'             => $broadcast['body'],
            'attachment_count' => (int) $broadcast['attachment_count'],
            'recipient_count'  => (int) $broadcast['recipient_count'],
            'created_at'       => $broadcast['created_at'],
        ];
    }
}
