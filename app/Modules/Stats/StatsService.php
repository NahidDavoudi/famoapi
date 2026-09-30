<?php

namespace App\Modules\Stats;

use App\Core\ApiException;
use App\Core\Pagination;
use App\Modules\Bot\IranDay;
use App\Modules\Students\Student;
use App\Modules\Threads\Message;
use App\Modules\Threads\MessageAttachment;
use App\Modules\Threads\Thread;

/**
 * Module 6: admin statistics. Stats endpoints expose aggregates only. Reading
 * message content is a separate endpoint behind a dedicated permission
 * (ContentAccessMiddleware), disabled by default.
 */
class StatsService
{
    public function reportsOverview(?string $from, ?string $to): array
    {
        [$from, $to] = $this->resolveRange($from, $to);
        $days = IranDay::datesBetween($from, $to);
        $byDay = Stats::reportsByDay($from, $to);
        $activeStudents = Stats::activeStudentCount();

        $series = [];
        $totalReports = 0;
        foreach ($days as $day) {
            $count = $byDay[$day] ?? 0;
            $totalReports += $count;
            $series[] = [
                'day'             => $day,
                'day_jalali'      => IranDay::jalali($day),
                'weekday'         => IranDay::weekdayName($day),
                'report_count'    => $count,
                'active_students' => $activeStudents,
                'rate'            => $this->rate($count, $activeStudents),
            ];
        }

        $slots = $activeStudents * max(1, count($days));

        return [
            'from'             => $from,
            'to'               => $to,
            'active_students'  => $activeStudents,
            'days_count'       => count($days),
            'total_reports'    => $totalReports,
            'overall_rate'     => $this->rate($totalReports, $slots),
            'days'             => $series,
        ];
    }

    public function reportsByMajor(?string $from, ?string $to): array
    {
        [$from, $to] = $this->resolveRange($from, $to);
        $daysCount = max(1, count(IranDay::datesBetween($from, $to)));
        $reports = Stats::reportsByField($from, $to);
        $active = Stats::activeCountsByField();

        $items = [];
        foreach ($active as $field => $activeCount) {
            $count = $reports[$field] ?? 0;
            $items[] = [
                'field'           => $field,
                'active_students' => $activeCount,
                'report_count'    => $count,
                'rate'            => $this->rate($count, $activeCount * $daysCount),
            ];
        }

        usort($items, static fn ($a, $b) => $b['rate'] <=> $a['rate']);

        return ['from' => $from, 'to' => $to, 'items' => $items];
    }

    public function reportsBySupporter(?string $from, ?string $to): array
    {
        [$from, $to] = $this->resolveRange($from, $to);
        $daysCount = max(1, count(IranDay::datesBetween($from, $to)));
        $reports = Stats::reportsBySupporter($from, $to);
        $assigned = Stats::assignedCountsBySupporter();

        $ids = array_values(array_unique(array_merge(array_keys($reports), array_keys($assigned))));
        $names = Stats::supporterNames($ids);

        $items = [];
        foreach ($ids as $supporterId) {
            $activeCount = $assigned[$supporterId] ?? 0;
            $count = $reports[$supporterId] ?? 0;
            $items[] = [
                'supporter_id'     => $supporterId,
                'supporter_name'   => $names[$supporterId] ?? null,
                'assigned_students' => $activeCount,
                'report_count'     => $count,
                'rate'             => $this->rate($count, $activeCount * $daysCount),
            ];
        }

        usort($items, static fn ($a, $b) => $b['rate'] <=> $a['rate']);

        return ['from' => $from, 'to' => $to, 'items' => $items];
    }

    public function studentsWithNoReport(int $days, int $page, int $perPage, ?string $search = null): array
    {
        $days = max(1, min(365, $days));
        $to = IranDay::today();
        $from = IranDay::addDays($to, -($days - 1));

        $total = Stats::countStudentsWithNoReport($from, $to, $search);
        $pagination = Pagination::build($page, $perPage, $total);
        $items = Stats::studentsWithNoReport($from, $to, $pagination['page'], $pagination['per_page'], $search);

        return [
            'data' => [
                'from'      => $from,
                'to'        => $to,
                'days'      => $days,
                'students'  => $items,
            ],
            'pagination' => $pagination,
        ];
    }

    public function supporterPerformance(?string $from, ?string $to): array
    {
        [$from, $to] = $this->resolveRange($from, $to);

        $threadRows = Stats::threadFirstTimes($from, $to);
        $messages = Stats::supporterMessageCounts($from, $to);
        $broadcasts = Stats::broadcastCounts($from, $to);
        $backlog = Stats::unreadBacklogBySupporter();
        $assigned = Stats::assignedCountsBySupporter();
        $reports = Stats::reportsBySupporter($from, $to);

        $grouped = [];
        foreach ($threadRows as $row) {
            $grouped[(int) $row['supporter_id']][] = $row;
        }

        $ids = array_values(array_unique(array_merge(
            array_keys($assigned),
            array_keys($grouped),
            array_keys($messages),
            array_keys($broadcasts),
            array_keys($backlog),
            array_keys($reports)
        )));
        $names = Stats::supporterNames($ids);

        $items = [];
        foreach ($ids as $supporterId) {
            $threadsWithReports = 0;
            $threadsWithReply = 0;
            $unanswered = 0;
            $oldestUnanswered = null;
            $deltas = [];

            foreach ($grouped[$supporterId] ?? [] as $row) {
                if ($row['first_student'] === null) {
                    continue;
                }
                $threadsWithReports++;
                if ($row['first_supporter'] !== null) {
                    $threadsWithReply++;
                    $deltas[] = strtotime((string) $row['first_supporter']) - strtotime((string) $row['first_student']);
                } else {
                    $unanswered++;
                    if ($oldestUnanswered === null || $row['day'] < $oldestUnanswered) {
                        $oldestUnanswered = $row['day'];
                    }
                }
            }

            $items[] = [
                'supporter_id'             => $supporterId,
                'supporter_name'           => $names[$supporterId] ?? null,
                'assigned_students'        => $assigned[$supporterId] ?? 0,
                'report_count'             => $reports[$supporterId] ?? 0,
                'threads_with_reports'     => $threadsWithReports,
                'threads_with_reply'       => $threadsWithReply,
                'reply_rate'               => $this->rate($threadsWithReply, max(1, $threadsWithReports)),
                'unanswered_threads'       => $unanswered,
                'oldest_unanswered_day'    => $oldestUnanswered,
                'avg_first_reply_seconds'  => $deltas !== [] ? (int) round(array_sum($deltas) / count($deltas)) : null,
                'median_first_reply_seconds' => $this->median($deltas),
                'messages_sent'            => $messages[$supporterId] ?? 0,
                'broadcasts_sent'          => $broadcasts[$supporterId] ?? 0,
                'unread_backlog'           => $backlog[$supporterId] ?? 0,
            ];
        }

        return ['from' => $from, 'to' => $to, 'items' => $items];
    }

    public function studentHistory(int $studentId, ?string $from, ?string $to): array
    {
        $student = Student::findById($studentId);
        if (!$student) {
            throw new ApiException('دانش‌آموز یافت نشد', 404, 'NOT_FOUND');
        }

        [$from, $to] = $this->resolveRange($from, $to);
        $rows = Stats::studentHistory($studentId, $from, $to);
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['day']] = $row;
        }

        $days = [];
        foreach (IranDay::datesBetween($from, $to) as $day) {
            $row = $byDay[$day] ?? null;
            $reportCount = $row ? (int) $row['report_count'] : 0;
            $replyCount = $row ? (int) $row['reply_count'] : 0;
            $firstReplySeconds = null;
            if ($row && $row['first_report_at'] !== null && $row['first_reply_at'] !== null) {
                $firstReplySeconds = strtotime((string) $row['first_reply_at']) - strtotime((string) $row['first_report_at']);
            }

            $days[] = [
                'day'                 => $day,
                'day_jalali'          => IranDay::jalali($day),
                'report_submitted'    => $reportCount > 0,
                'report_count'        => $reportCount,
                'reply_count'         => $replyCount,
                'has_reply'           => $replyCount > 0,
                'first_reply_seconds' => $firstReplySeconds,
            ];
        }

        return [
            'student' => ['id' => (int) $student['id'], 'name' => $student['name'], 'grade' => (int) $student['grade'], 'field' => $student['field']],
            'from'    => $from,
            'to'      => $to,
            'days'    => $days,
        ];
    }

    /** Content-reading endpoint: returns message bodies + attachment refs. */
    public function studentThreadContent(int $studentId, string $day, int $page, int $perPage): array
    {
        $student = Student::findById($studentId);
        if (!$student) {
            throw new ApiException('دانش‌آموز یافت نشد', 404, 'NOT_FOUND');
        }
        if (!IranDay::isValidDate($day)) {
            throw new ApiException('تاریخ نامعتبر است', 422, 'INVALID_DAY');
        }

        $thread = Thread::findByStudentDay($studentId, $day);
        $total = $thread ? Message::countForThread((int) $thread['id']) : 0;
        $pagination = Pagination::build($page, $perPage, $total);

        $messages = [];
        if ($thread) {
            $rows = Message::findForThread((int) $thread['id'], $pagination['page'], $pagination['per_page']);
            $ids = array_map(static fn (array $r) => (int) $r['id'], $rows);
            $attachments = MessageAttachment::groupedByMessageIds($ids);

            $messages = array_map(static fn (array $row) => [
                'id'                => (int) $row['id'],
                'sender_role'       => $row['sender_role'],
                'sender_account_id' => $row['sender_account_id'] !== null ? (int) $row['sender_account_id'] : null,
                'body'              => $row['body'],
                'media_group_id'    => $row['media_group_id'],
                'is_broadcast'      => (int) $row['is_broadcast'] === 1,
                'read_by_student'   => (int) $row['read_by_student'] === 1,
                'read_by_supporter' => (int) $row['read_by_supporter'] === 1,
                'created_at'        => $row['created_at'],
                'attachments'       => array_map(static fn (array $a) => [
                    'kind'       => $a['kind'],
                    'tg_file_id' => $a['tg_file_id'],
                    'file_name'  => $a['file_name'],
                    'mime_type'  => $a['mime_type'],
                    'file_size'  => $a['file_size'] !== null ? (int) $a['file_size'] : null,
                ], $attachments[(int) $row['id']] ?? []),
            ], $rows);
        }

        return [
            'data' => [
                'student'    => ['id' => (int) $student['id'], 'name' => $student['name']],
                'day'        => $day,
                'day_jalali' => IranDay::jalali($day),
                'thread_id'  => $thread ? (int) $thread['id'] : null,
                'messages'   => $messages,
            ],
            'pagination' => $pagination,
        ];
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @return array{0:string,1:string}
     */
    private function resolveRange(?string $from, ?string $to): array
    {
        $today = IranDay::today();
        $to = $to !== null && IranDay::isValidDate($to) ? $to : $today;
        $from = $from !== null && IranDay::isValidDate($from) ? $from : IranDay::addDays($to, -6);

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    private function rate(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round($numerator / $denominator, 4);
    }

    /**
     * @param int[] $values
     */
    private function median(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (int) $values[$middle];
        }

        return (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }
}
