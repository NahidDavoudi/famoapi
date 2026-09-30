<?php

namespace App\Modules\Threads;

use App\Core\ApiException;
use App\Core\Pagination;
use App\Modules\Assignments\Assignment;
use App\Modules\Bot\IranDay;
use App\Modules\Outbox\OutboxService;

/**
 * Module 3: daily threads and messages.
 *
 * All access control is server-side: a student can only touch their own
 * threads; a supporter can only touch students they are currently assigned or
 * were the snapshot supporter for on the thread being read.
 */
class ThreadService
{
    private const MAX_ATTACHMENTS = 10;

    private OutboxService $outbox;

    public function __construct(?OutboxService $outbox = null)
    {
        $this->outbox = $outbox ?? new OutboxService();
    }

    public function sendStudentMessage(array $actor, array $data): array
    {
        $studentId = $this->requireStudent($actor);
        [$body, $attachments, $mediaGroupId] = $this->parseContent($data);

        $assignment = Assignment::findActiveByStudent($studentId);
        if (!$assignment) {
            throw new ApiException('هنوز پشتیبانی برای شما تعیین نشده است', 409, 'NO_SUPPORTER_ASSIGNED');
        }

        $day = IranDay::today();
        $threadId = Thread::ensure($studentId, $day, (int) $assignment['supporter_id']);
        $messageId = Message::create(
            $threadId,
            $studentId,
            'student',
            $studentId,
            $body,
            $mediaGroupId,
            false,
            true,
            false
        );
        MessageAttachment::createMany($messageId, $attachments);

        $this->safeEnqueue(fn () => $this->outbox->enqueueReportNotification(
            $studentId,
            (int) $assignment['supporter_id'],
            $messageId,
            $day,
            $body,
            $attachments
        ));

        return $this->messageResponse($messageId);
    }

    public function getDay(array $actor, ?int $studentId, ?string $day, int $page, int $perPage): array
    {
        $studentId = $this->resolveStudentForActor($actor, $studentId);
        $day = $this->resolveDay($day ?? IranDay::today());

        $thread = Thread::findByStudentDay($studentId, $day);
        $pagination = Pagination::build($page, $perPage, $thread ? Message::countForThread((int) $thread['id']) : 0);

        $messages = [];
        $reportSubmitted = false;
        $replyUnread = 0;
        $studentUnread = 0;

        if ($thread) {
            $rows = Message::findForThread((int) $thread['id'], $pagination['page'], $pagination['per_page']);
            $messages = $this->messagePayloads($rows, $day);
            foreach ($rows as $row) {
                if ($row['sender_role'] === 'student') {
                    $reportSubmitted = true;
                } elseif ((int) $row['read_by_student'] === 0) {
                    $replyUnread++;
                }
                if ($row['sender_role'] === 'student' && (int) $row['read_by_supporter'] === 0) {
                    $studentUnread++;
                }
            }
        }

        return [
            'student_id'       => $studentId,
            'day'              => $day,
            'day_jalali'       => IranDay::jalali($day),
            'weekday'          => IranDay::weekdayName($day),
            'thread_id'        => $thread ? (int) $thread['id'] : null,
            'supporter_id'     => $thread['supporter_id'] !== null ? (int) $thread['supporter_id'] : null,
            'report_submitted' => $reportSubmitted,
            'unread_replies'   => $replyUnread,
            'unread_by_supporter' => $studentUnread,
            'messages'         => $messages,
            'pagination'       => $pagination,
        ];
    }

    public function weekly(array $actor, ?int $studentId, ?string $weekStart): array
    {
        $studentId = $this->resolveStudentForActor($actor, $studentId);

        $start = $weekStart !== null && IranDay::isValidDate($weekStart)
            ? IranDay::weekStart($weekStart)
            : IranDay::weekStart();

        $days = IranDay::weekDays($start);
        $today = IranDay::today();

        $aggregates = Message::dayAggregates($studentId, $days[0], $days[6]);
        $threads = Thread::daysForStudent($studentId, $days[0], $days[6]);

        $result = [];
        foreach ($days as $day) {
            $agg = $aggregates[$day] ?? ['report_count' => 0, 'reply_count' => 0, 'reply_unread' => 0];
            $reportSubmitted = $agg['report_count'] > 0;

            $result[] = [
                'day'              => $day,
                'day_jalali'       => IranDay::jalali($day),
                'weekday'          => IranDay::weekdayName($day),
                'state'            => IranDay::dayState($day, $reportSubmitted, $today),
                'report_submitted' => $reportSubmitted,
                'has_reply'        => $agg['reply_count'] > 0,
                'reply_unread'     => $agg['reply_unread'] > 0,
                'unread_replies'   => $agg['reply_unread'],
                'supporter_id'     => isset($threads[$day]['supporter_id']) && $threads[$day]['supporter_id'] !== null
                    ? (int) $threads[$day]['supporter_id']
                    : null,
            ];
        }

        return [
            'student_id'       => $studentId,
            'week_start'       => $days[0],
            'week_start_jalali' => IranDay::jalali($days[0]),
            'days'             => $result,
        ];
    }

    public function markRead(array $actor, ?int $studentId, ?string $day): array
    {
        $role = $actor['role'];

        if ($role === 'student') {
            $selfId = $this->requireStudent($actor);
            $threadId = null;
            if ($day !== null) {
                $thread = Thread::findByStudentDay($selfId, $this->resolveDay($day));
                if (!$thread) {
                    return ['marked' => 0];
                }
                $threadId = (int) $thread['id'];
            }

            return ['marked' => Message::markReadByStudent($selfId, $threadId)];
        }

        $supporterId = $this->requireSupporter($actor);
        if ($studentId === null) {
            throw new ApiException('شناسه دانش‌آموز الزامی است', 422, 'VALIDATION_ERROR');
        }
        $this->assertSupporterAccess($supporterId, $studentId, true);

        $threadId = null;
        if ($day !== null) {
            $thread = Thread::findByStudentDay($studentId, $this->resolveDay($day));
            if (!$thread) {
                return ['marked' => 0];
            }
            $threadId = (int) $thread['id'];
        }

        return ['marked' => Message::markReadBySupporter($studentId, $threadId)];
    }

    public function inbox(array $actor, int $page, int $perPage): array
    {
        $supporterId = $this->requireSupporter($actor);
        $total = Message::countInboxStudents($supporterId);
        $pagination = Pagination::build($page, $perPage, $total);

        $rows = Message::unreadInbox($supporterId, $pagination['page'], $pagination['per_page']);
        $students = array_map(static fn (array $row) => [
            'student_id'      => (int) $row['student_id'],
            'student_name'    => $row['student_name'],
            'grade'           => $row['grade'] !== null ? (int) $row['grade'] : null,
            'field'           => $row['field'],
            'unread_count'    => (int) $row['unread_count'],
            'last_message_at' => $row['last_message_at'],
        ], $rows);

        return [
            'total_students' => $total,
            'students'       => $students,
            'pagination'     => $pagination,
        ];
    }

    public function students(array $actor): array
    {
        $supporterId = $this->requireSupporter($actor);
        $rows = Assignment::activeStudentsForSupporter($supporterId);
        $ids = array_map(static fn (array $row) => (int) $row['id'], $rows);

        $today = IranDay::today();
        $status = Message::dayStatusForStudents($ids, $today);
        $unread = Message::unreadCountsForStudents($ids);

        $students = array_map(function (array $row) use ($status, $unread) {
            $id = (int) $row['id'];
            $reportCount = $status[$id]['report_count'] ?? 0;
            $replyCount = $status[$id]['reply_count'] ?? 0;

            return [
                'student_id'       => $id,
                'name'             => $row['name'],
                'grade'            => (int) $row['grade'],
                'field'            => $row['field'],
                'report_submitted' => $reportCount > 0,
                'has_reply'        => $replyCount > 0,
                'unread_count'     => $unread[$id] ?? 0,
            ];
        }, $rows);

        return [
            'total_students' => count($students),
            'day'            => $today,
            'day_jalali'     => IranDay::jalali($today),
            'students'       => $students,
        ];
    }

    public function unreadForStudent(array $actor, int $studentId, int $limit = 200): array
    {
        $supporterId = $this->requireSupporter($actor);
        $this->assertSupporterAccess($supporterId, $studentId, true);

        $rows = Message::unreadForStudent($studentId, $supporterId, $limit);
        $messages = array_map(function (array $row) {
            $payload = $this->messagePayload(
                $row,
                [],
                $row['day'] ?? null
            );
            return $payload;
        }, $rows);

        // Attach media in one batch.
        $ids = array_map(static fn (array $row) => (int) $row['id'], $rows);
        $grouped = MessageAttachment::groupedByMessageIds($ids);
        foreach ($messages as &$message) {
            $message['attachments'] = array_map(
                [$this, 'attachmentPayload'],
                $grouped[$message['id']] ?? []
            );
        }
        unset($message);

        return [
            'student_id'   => $studentId,
            'unread_count' => count($messages),
            'messages'     => $messages,
        ];
    }

    public function reply(array $actor, array $data): array
    {
        $supporterId = $this->requireSupporter($actor);
        $studentId = (int) ($data['student_id'] ?? 0);
        if ($studentId <= 0) {
            throw new ApiException('شناسه دانش‌آموز الزامی است', 422, 'VALIDATION_ERROR');
        }
        $this->assertSupporterAccess($supporterId, $studentId, true);

        [$body, $attachments, $mediaGroupId] = $this->parseContent($data);

        $day = isset($data['day']) && is_string($data['day']) && $data['day'] !== ''
            ? $this->resolveDay($data['day'])
            : (Message::latestUnreadReportDay($studentId, $supporterId) ?? IranDay::today());

        $active = Assignment::findActiveByStudent($studentId);
        $snapshot = $active ? (int) $active['supporter_id'] : $supporterId;
        $threadId = Thread::ensure($studentId, $day, $snapshot);

        $messageId = Message::create(
            $threadId,
            $studentId,
            'supporter',
            $supporterId,
            $body,
            $mediaGroupId,
            false,
            false,
            true
        );
        MessageAttachment::createMany($messageId, $attachments);

        $this->safeEnqueue(fn () => $this->outbox->enqueueReplyNotification(
            $studentId,
            $supporterId,
            $messageId,
            $day,
            $body,
            $attachments
        ));

        return $this->messageResponse($messageId);
    }

    // ---------------------------------------------------------------- helpers

    private function safeEnqueue(callable $enqueue): void
    {
        try {
            $enqueue();
        } catch (\Throwable $e) {
            error_log('[' . date('Y-m-d H:i:s') . '] outbox enqueue failed: ' . $e->getMessage() . PHP_EOL, 3, __DIR__ . '/../../../storage/logs/app.log');
        }
    }

    private function requireStudent(array $actor): int
    {
        if (($actor['role'] ?? '') !== 'student') {
            throw new ApiException('این عملیات فقط برای دانش‌آموزان مجاز است', 403, 'FORBIDDEN');
        }

        return (int) $actor['account_id'];
    }

    private function requireSupporter(array $actor): int
    {
        if (($actor['role'] ?? '') !== 'supporter') {
            throw new ApiException('این عملیات فقط برای پشتیبانان مجاز است', 403, 'FORBIDDEN');
        }

        return (int) $actor['account_id'];
    }

    private function resolveStudentForActor(array $actor, ?int $studentId): int
    {
        if (($actor['role'] ?? '') === 'student') {
            $selfId = (int) $actor['account_id'];
            if ($studentId !== null && $studentId !== $selfId) {
                throw new ApiException('دسترسی غیرمجاز', 403, 'FORBIDDEN');
            }

            return $selfId;
        }

        $supporterId = $this->requireSupporter($actor);
        if ($studentId === null || $studentId <= 0) {
            throw new ApiException('شناسه دانش‌آموز الزامی است', 422, 'VALIDATION_ERROR');
        }
        $this->assertSupporterAccess($supporterId, $studentId, true);

        return $studentId;
    }

    private function assertSupporterAccess(int $supporterId, int $studentId, bool $allowSnapshot): void
    {
        $active = Assignment::findActiveByStudent($studentId);
        if ($active && (int) $active['supporter_id'] === $supporterId) {
            return;
        }

        if ($allowSnapshot && Thread::existsForSupporter($studentId, $supporterId)) {
            return;
        }

        throw new ApiException('دسترسی غیرمجاز به این دانش‌آموز', 403, 'FORBIDDEN');
    }

    private function resolveDay(string $day): string
    {
        if (!IranDay::isValidDate($day)) {
            throw new ApiException('تاریخ نامعتبر است', 422, 'INVALID_DAY');
        }

        return $day;
    }

    /**
     * @return array{0:?string,1:array,2:?string}
     */
    private function parseContent(array $data): array
    {
        $body = isset($data['text']) ? trim((string) $data['text']) : '';
        $body = $body === '' ? null : $body;

        $attachments = $this->normalizeAttachments($data['attachments'] ?? []);

        if ($body === null && $attachments === []) {
            throw new ApiException('متن یا پیوست پیام الزامی است', 422, 'EMPTY_MESSAGE');
        }

        $mediaGroupId = isset($data['media_group_id']) && $data['media_group_id'] !== ''
            ? (string) $data['media_group_id']
            : null;

        return [$body, $attachments, $mediaGroupId];
    }

    /**
     * @return array<int,array>
     */
    private function normalizeAttachments(mixed $attachments): array
    {
        if ($attachments === null || $attachments === []) {
            return [];
        }
        if (!is_array($attachments)) {
            throw new ApiException('ساختار پیوست نامعتبر است', 422, 'INVALID_ATTACHMENT');
        }
        if (count($attachments) > self::MAX_ATTACHMENTS) {
            throw new ApiException('تعداد پیوست‌ها بیش از حد مجاز است', 422, 'TOO_MANY_ATTACHMENTS');
        }

        $normalized = [];
        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                throw new ApiException('ساختار پیوست نامعتبر است', 422, 'INVALID_ATTACHMENT');
            }
            $kind = (string) ($attachment['kind'] ?? '');
            $fileId = trim((string) ($attachment['tg_file_id'] ?? ''));
            if (!in_array($kind, MessageAttachment::KINDS, true) || $fileId === '') {
                throw new ApiException('پیوست نامعتبر است (نوع یا شناسه فایل)', 422, 'INVALID_ATTACHMENT');
            }

            $normalized[] = [
                'kind'       => $kind,
                'tg_file_id' => $fileId,
                'file_name'  => isset($attachment['file_name']) ? (string) $attachment['file_name'] : null,
                'mime_type'  => isset($attachment['mime_type']) ? (string) $attachment['mime_type'] : null,
                'file_size'  => isset($attachment['file_size']) ? (int) $attachment['file_size'] : null,
            ];
        }

        return $normalized;
    }

    private function messageResponse(int $messageId): array
    {
        $message = Message::findById($messageId);
        if (!$message) {
            throw new ApiException('پیام یافت نشد', 404, 'NOT_FOUND');
        }

        $thread = Thread::findById((int) $message['thread_id']);
        $day = $thread['day'] ?? null;
        $attachments = MessageAttachment::groupedByMessageIds([$messageId]);

        return $this->messagePayload($message, $attachments[$messageId] ?? [], $day);
    }

    /**
     * @param array<int,array> $rows
     * @return array<int,array>
     */
    private function messagePayloads(array $rows, ?string $day): array
    {
        $ids = array_map(static fn (array $row) => (int) $row['id'], $rows);
        $grouped = MessageAttachment::groupedByMessageIds($ids);

        return array_map(fn (array $row) => $this->messagePayload(
            $row,
            $grouped[(int) $row['id']] ?? [],
            $day
        ), $rows);
    }

    private function messagePayload(array $message, array $attachments, ?string $day): array
    {
        return [
            'id'                => (int) $message['id'],
            'thread_id'         => (int) $message['thread_id'],
            'day'               => $day,
            'day_jalali'        => $day !== null ? IranDay::jalali($day) : null,
            'sender_role'       => $message['sender_role'],
            'sender_account_id' => $message['sender_account_id'] !== null ? (int) $message['sender_account_id'] : null,
            'body'              => $message['body'],
            'media_group_id'    => $message['media_group_id'],
            'is_broadcast'      => (int) $message['is_broadcast'] === 1,
            'read_by_student'   => (int) $message['read_by_student'] === 1,
            'read_by_supporter' => (int) $message['read_by_supporter'] === 1,
            'created_at'        => $message['created_at'],
            'attachments'       => array_map([$this, 'attachmentPayload'], $attachments),
        ];
    }

    private function attachmentPayload(array $attachment): array
    {
        return [
            'id'         => (int) $attachment['id'],
            'kind'       => $attachment['kind'],
            'tg_file_id' => $attachment['tg_file_id'],
            'file_name'  => $attachment['file_name'],
            'mime_type'  => $attachment['mime_type'],
            'file_size'  => $attachment['file_size'] !== null ? (int) $attachment['file_size'] : null,
        ];
    }
}
