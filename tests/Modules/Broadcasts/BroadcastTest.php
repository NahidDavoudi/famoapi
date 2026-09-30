<?php

namespace Tests\Modules\Broadcasts;

use App\Modules\Assignments\Assignment;
use App\Modules\Bot\IranDay;
use App\Modules\Bot\TelegramLink;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class BroadcastTest extends TestCase
{
    private const TG_BASE = 850000000;

    private ?string $originalLimit = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalLimit = $_ENV['BOT_BROADCAST_DAILY_LIMIT'] ?? null;
        $_ENV['BOT_BROADCAST_DAILY_LIMIT'] = '200';

        $this->db->exec('DELETE FROM bot_outbox');
        $this->db->exec("DELETE FROM report_broadcasts WHERE supporter_id BETWEEN 9400000 AND 9400999");
        $this->db->exec("DELETE FROM report_broadcast_recipients WHERE student_id IN (SELECT id FROM students WHERE phone LIKE '09994444%')");
        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id BETWEEN 850000000 AND 850999999");
        $this->db->exec("DELETE FROM users WHERE username LIKE '09994444%'");
        $this->db->exec("DELETE FROM students WHERE phone LIKE '09994444%'");
        $this->db->exec("DELETE FROM student_supporter_assignments WHERE supporter_id BETWEEN 9400000 AND 9400999");
        $this->db->exec("DELETE FROM supporters WHERE id BETWEEN 9400000 AND 9400999");
    }

    protected function tearDown(): void
    {
        if ($this->originalLimit === null) {
            unset($_ENV['BOT_BROADCAST_DAILY_LIMIT']);
        } else {
            $_ENV['BOT_BROADCAST_DAILY_LIMIT'] = $this->originalLimit;
        }

        parent::tearDown();
    }

    private function seedSupporter(int $id): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO supporters (id, name, grade, field, phone, is_active) VALUES (?, ?, 12, ?, NULL, 1)'
        );
        $stmt->execute([$id, 'Supporter ' . $id, 'ریاضی']);
    }

    private function link(int $telegramUserId, string $role, int $accountId): void
    {
        TelegramLink::create($telegramUserId, $telegramUserId, $role, $accountId);
    }

    private function supporterCall(string $method, string $uri, int $tg, array $body = []): ResponseInterface
    {
        return $this->handleRequest($this->jsonRequest($method, $uri, $body, [
            'X-Bot-Key'          => 'test-bot-key',
            'X-Bot-Role'         => 'supporter',
            'X-Telegram-User-Id' => (string) $tg,
            'X-Telegram-Chat-Id' => (string) $tg,
        ]));
    }

    private function studentSend(int $tg, string $text): void
    {
        $this->handleRequest($this->jsonRequest('POST', '/api/v1/bot/threads/messages', ['text' => $text], [
            'X-Bot-Key'          => 'test-bot-key',
            'X-Bot-Role'         => 'student',
            'X-Telegram-User-Id' => (string) $tg,
            'X-Telegram-Chat-Id' => (string) $tg,
        ]));
    }

    private function seedAssignedStudent(string $phone, int $supporterId, int $studentTg, ?int $supporterTg = null): int
    {
        $studentId = $this->seedStudent(['phone' => $phone, 'name' => 'Broadcast Student']);
        Assignment::insert($studentId, $supporterId, null, 'test');
        if ($studentTg > 0) {
            $this->link($studentTg, 'student', $studentId);
        }
        if ($supporterTg !== null && $supporterTg > 0) {
            $this->link($supporterTg, 'supporter', $supporterId);
        }

        return $studentId;
    }

    public function testPreviewAllStudentsAndNoReportToday(): void
    {
        $supporterId = 9400001;
        $this->seedSupporter($supporterId);
        $studentA = $this->seedAssignedStudent('09994444001', $supporterId, self::TG_BASE + 1, self::TG_BASE + 101);
        $studentB = $this->seedAssignedStudent('09994444002', $supporterId, self::TG_BASE + 2);

        // Student A submits a report today; B does not.
        $this->studentSend(self::TG_BASE + 1, 'report A');

        $all = $this->supporterCall('POST', '/api/v1/bot/broadcasts/preview', self::TG_BASE + 101, ['audience' => 'all_students']);
        $allData = $this->assertJsonResponse($all, 200, ['recipient_count', 'recipients']);
        $this->assertSame(2, $allData['data']['recipient_count']);

        $noReport = $this->supporterCall('POST', '/api/v1/bot/broadcasts/preview', self::TG_BASE + 101, ['audience' => 'no_report_today']);
        $noReportData = $this->assertJsonResponse($noReport, 200, ['recipient_count', 'recipients']);
        $this->assertSame(1, $noReportData['data']['recipient_count']);
        $this->assertSame($studentB, $noReportData['data']['recipients'][0]['student_id']);

        $this->assertNotNull($studentA);
    }

    public function testConfirmCreatesBroadcastMessagesAndOutboxItems(): void
    {
        $supporterId = 9400002;
        $this->seedSupporter($supporterId);
        $studentA = $this->seedAssignedStudent('09994444003', $supporterId, self::TG_BASE + 3, self::TG_BASE + 102);
        $studentB = $this->seedAssignedStudent('09994444004', $supporterId, self::TG_BASE + 4);

        $confirm = $this->supporterCall('POST', '/api/v1/bot/broadcasts/confirm', self::TG_BASE + 102, [
            'audience' => 'all_students',
            'text'     => 'پیام گروهی',
        ]);
        $data = $this->assertJsonResponse($confirm, 201, ['broadcast_id', 'recipient_count', 'enqueued']);
        $this->assertSame(2, $data['data']['recipient_count']);
        $this->assertSame(2, $data['data']['enqueued']);
        $broadcastId = $data['data']['broadcast_id'];

        foreach ([$studentA, $studentB] as $studentId) {
            $message = $this->db->query(
                "SELECT m.sender_role, m.is_broadcast, m.read_by_student
                 FROM report_messages m
                 JOIN report_threads t ON t.id = m.thread_id
                 WHERE t.student_id = {$studentId} AND t.day = '" . IranDay::today() . "'
                 ORDER BY m.id DESC LIMIT 1"
            )->fetch();
            $this->assertSame('broadcast', $message['sender_role']);
            $this->assertSame(1, (int) $message['is_broadcast']);
            $this->assertSame(0, (int) $message['read_by_student']);
        }

        $outboxCount = (int) $this->db->query(
            "SELECT COUNT(*) FROM bot_outbox WHERE kind = 'broadcast'"
        )->fetchColumn();
        $this->assertSame(2, $outboxCount);

        $recipients = $this->db->query(
            "SELECT status, outbox_id FROM report_broadcast_recipients WHERE broadcast_id = {$broadcastId}"
        )->fetchAll();
        $this->assertCount(2, $recipients);
        foreach ($recipients as $row) {
            $this->assertSame('pending', $row['status']);
            $this->assertNotNull($row['outbox_id']);
        }
    }

    public function testPerRecipientResultSyncsFromOutboxReport(): void
    {
        $supporterId = 9400003;
        $this->seedSupporter($supporterId);
        $studentId = $this->seedAssignedStudent('09994444005', $supporterId, self::TG_BASE + 5, self::TG_BASE + 103);

        $confirm = $this->supporterCall('POST', '/api/v1/bot/broadcasts/confirm', self::TG_BASE + 103, [
            'audience' => 'all_students',
            'text'     => 'hi',
        ]);
        $broadcastId = $this->assertJsonResponse($confirm, 201)['data']['broadcast_id'];

        $claim = $this->handleRequest($this->jsonRequest('POST', '/api/v1/bot/outbox/claim', ['limit' => 5, 'worker_id' => 'W'], [
            'X-Bot-Key' => 'test-bot-key',
        ]));
        $items = $this->assertJsonResponse($claim, 200)['data']['items'];
        $this->assertCount(1, $items);
        $outboxId = $items[0]['id'];

        $report = $this->handleRequest($this->jsonRequest('POST', '/api/v1/bot/outbox/report', [
            'worker_id' => 'W',
            'results'   => [['id' => $outboxId, 'status' => 'sent', 'telegram_message_id' => '999']],
        ], ['X-Bot-Key' => 'test-bot-key']));
        $this->assertSame('sent', $this->assertJsonResponse($report, 200)['data']['results'][0]['result']);

        $get = $this->supporterCall('GET', '/api/v1/bot/broadcasts/' . $broadcastId, self::TG_BASE + 103);
        $getData = $this->assertJsonResponse($get, 200, ['broadcast', 'summary', 'recipients']);
        $this->assertSame(1, $getData['data']['summary']['sent']);
        $this->assertSame('sent', $getData['data']['recipients'][0]['status']);
        $this->assertSame('999', $getData['data']['recipients'][0]['telegram_message_id']);
        $this->assertSame($studentId, $getData['data']['recipients'][0]['student_id']);
    }

    public function testDailyLimitIsEnforced(): void
    {
        $_ENV['BOT_BROADCAST_DAILY_LIMIT'] = '1';
        $supporterId = 9400004;
        $this->seedSupporter($supporterId);
        $this->seedAssignedStudent('09994444006', $supporterId, self::TG_BASE + 6, self::TG_BASE + 104);

        $first = $this->supporterCall('POST', '/api/v1/bot/broadcasts/confirm', self::TG_BASE + 104, [
            'audience' => 'all_students',
            'text'     => 'one',
        ]);
        $this->assertJsonResponse($first, 201);

        $second = $this->supporterCall('POST', '/api/v1/bot/broadcasts/confirm', self::TG_BASE + 104, [
            'audience' => 'all_students',
            'text'     => 'two',
        ]);
        $this->assertJsonResponse($second, 429, null, 'BROADCAST_DAILY_LIMIT');
    }

    public function testBlockedAndUnlinkedRecipientsAreHandled(): void
    {
        $supporterId = 9400005;
        $this->seedSupporter($supporterId);
        $blocked = $this->seedAssignedStudent('09994444007', $supporterId, self::TG_BASE + 7, self::TG_BASE + 105);
        TelegramLink::setBlockedByRoleAndAccount('student', $blocked, true);
        $unlinked = $this->seedAssignedStudent('09994444008', $supporterId, 0);

        $confirm = $this->supporterCall('POST', '/api/v1/bot/broadcasts/confirm', self::TG_BASE + 105, [
            'audience' => 'all_students',
            'text'     => 'test',
        ]);
        $data = $this->assertJsonResponse($confirm, 201, ['enqueued', 'blocked', 'failed']);
        $this->assertSame(0, $data['data']['enqueued']);
        $this->assertSame(1, $data['data']['blocked']);
        $this->assertSame(1, $data['data']['failed']);

        $rows = $this->db->query(
            "SELECT student_id, status FROM report_broadcast_recipients WHERE broadcast_id = {$data['data']['broadcast_id']}"
        )->fetchAll();
        $statusByStudent = [];
        foreach ($rows as $row) {
            $statusByStudent[(int) $row['student_id']] = $row['status'];
        }
        $this->assertSame('blocked', $statusByStudent[$blocked]);
        $this->assertSame('failed', $statusByStudent[$unlinked]);
    }

    public function testAccessControl(): void
    {
        $supporterA = 9400006;
        $supporterB = 9400007;
        $this->seedSupporter($supporterA);
        $this->seedSupporter($supporterB);
        $this->link(self::TG_BASE + 106, 'supporter', $supporterA);
        $this->link(self::TG_BASE + 107, 'supporter', $supporterB);
        $this->seedAssignedStudent('09994444009', $supporterA, self::TG_BASE + 9);

        $confirm = $this->supporterCall('POST', '/api/v1/bot/broadcasts/confirm', self::TG_BASE + 106, [
            'audience' => 'all_students',
            'text'     => 'mine',
        ]);
        $broadcastId = $this->assertJsonResponse($confirm, 201)['data']['broadcast_id'];

        // Wrong supporter cannot read it.
        $other = $this->supporterCall('GET', '/api/v1/bot/broadcasts/' . $broadcastId, self::TG_BASE + 107);
        $this->assertJsonResponse($other, 404, null, 'NOT_FOUND');

        // A student actor cannot preview.
        $asStudent = $this->handleRequest($this->jsonRequest('POST', '/api/v1/bot/broadcasts/preview', ['audience' => 'all_students'], [
            'X-Bot-Key'          => 'test-bot-key',
            'X-Bot-Role'         => 'student',
            'X-Telegram-User-Id' => (string) (self::TG_BASE + 9),
            'X-Telegram-Chat-Id' => (string) (self::TG_BASE + 9),
        ]));
        $this->assertJsonResponse($asStudent, 403, null, 'FORBIDDEN');
    }
}
