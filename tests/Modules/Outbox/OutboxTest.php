<?php

namespace Tests\Modules\Outbox;

use App\Modules\Assignments\Assignment;
use App\Modules\Bot\TelegramLink;
use App\Modules\Outbox\OutboxService;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class OutboxTest extends TestCase
{
    private const TG_BASE = 860000000;

    /** @var array<string,mixed> */
    private array $envBackup = [];

    private const ENV_KEYS = [
        'BOT_OUTBOX_MAX_ATTEMPTS',
        'BOT_OUTBOX_RETRY_BACKOFF',
        'BOT_OUTBOX_LOCK_TTL',
        'BOT_REPORT_NOTIFY_WINDOW',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::ENV_KEYS as $key) {
            $this->envBackup[$key] = $_ENV[$key] ?? null;
        }
        $_ENV['BOT_OUTBOX_MAX_ATTEMPTS'] = '3';
        $_ENV['BOT_OUTBOX_RETRY_BACKOFF'] = '60';
        $_ENV['BOT_OUTBOX_LOCK_TTL'] = '300';
        $_ENV['BOT_REPORT_NOTIFY_WINDOW'] = '300';

        // claim() is global across the outbox, so isolate tests by clearing it.
        $this->db->exec('DELETE FROM bot_outbox');
        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id BETWEEN 860000000 AND 860999999");
        $this->db->exec("DELETE FROM users WHERE username LIKE '09995555%'");
        $this->db->exec("DELETE FROM students WHERE phone LIKE '09995555%'");
        $this->db->exec("DELETE FROM student_supporter_assignments WHERE supporter_id BETWEEN 9500000 AND 9500999");
        $this->db->exec("DELETE FROM supporters WHERE id BETWEEN 9500000 AND 9500999");
    }

    protected function tearDown(): void
    {
        foreach (self::ENV_KEYS as $key) {
            if ($this->envBackup[$key] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $this->envBackup[$key];
            }
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

    private function call(string $method, string $uri, array $body = []): ResponseInterface
    {
        return $this->handleRequest($this->jsonRequest($method, $uri, $body, [
            'X-Bot-Key' => 'test-bot-key',
        ]));
    }

    /**
     * @return array<int,array>
     */
    private function outboxRows(string $kind, int $recipientAccountId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM bot_outbox WHERE kind = ? AND recipient_account_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$kind, $recipientAccountId]);

        return $stmt->fetchAll();
    }

    private function studentSend(int $telegramUserId, string $text): void
    {
        $this->handleRequest($this->jsonRequest('POST', '/api/v1/bot/threads/messages', ['text' => $text], [
            'X-Bot-Key'          => 'test-bot-key',
            'X-Bot-Role'         => 'student',
            'X-Telegram-User-Id' => (string) $telegramUserId,
            'X-Telegram-Chat-Id' => (string) $telegramUserId,
        ]));
    }

    private function studentWithSupporter(string $phone, int $supporterId, int $studentTg, int $supporterTg): int
    {
        $studentId = $this->seedStudent(['phone' => $phone, 'name' => 'Outbox Student']);
        $this->seedSupporter($supporterId);
        Assignment::insert($studentId, $supporterId, null, 'test');
        $this->link($studentTg, 'student', $studentId);
        $this->link($supporterTg, 'supporter', $supporterId);

        return $studentId;
    }

    public function testStudentMessageEnqueuesSupporterNotification(): void
    {
        $studentId = $this->studentWithSupporter('09995555001', 9500001, self::TG_BASE + 1, self::TG_BASE + 101);

        $this->studentSend(self::TG_BASE + 1, 'گزارش اول');

        $rows = $this->outboxRows('student_report', 9500001);
        $this->assertCount(1, $rows);
        $this->assertSame('supporter', $rows[0]['recipient_role']);
        $this->assertSame('pending', $rows[0]['status']);
        $this->assertSame(self::TG_BASE + 101, (int) $rows[0]['chat_id']);

        $payload = json_decode($rows[0]['payload_json'], true);
        $this->assertSame('گزارش اول', $payload['text']);
        $this->assertSame($studentId, $payload['meta']['student_id']);
    }

    public function testSupporterReplyEnqueuesStudentNotification(): void
    {
        $studentId = $this->studentWithSupporter('09995555002', 9500002, self::TG_BASE + 2, self::TG_BASE + 102);

        $this->studentSend(self::TG_BASE + 2, 'گزارش');
        $this->handleRequest($this->jsonRequest('POST', '/api/v1/bot/supporter/reply', ['student_id' => $studentId, 'text' => 'آفرین'], [
            'X-Bot-Key'          => 'test-bot-key',
            'X-Bot-Role'         => 'supporter',
            'X-Telegram-User-Id' => (string) (self::TG_BASE + 102),
            'X-Telegram-Chat-Id' => (string) (self::TG_BASE + 102),
        ]));

        $rows = $this->outboxRows('supporter_reply', $studentId);
        $this->assertCount(1, $rows);
        $this->assertSame('student', $rows[0]['recipient_role']);
        $payload = json_decode($rows[0]['payload_json'], true);
        $this->assertSame('آفرین', $payload['text']);
    }

    public function testSupporterNotificationDeduplicatedWithinWindow(): void
    {
        $this->studentWithSupporter('09995555003', 9500003, self::TG_BASE + 3, self::TG_BASE + 103);

        $_ENV['BOT_REPORT_NOTIFY_WINDOW'] = '3600';
        $this->studentSend(self::TG_BASE + 3, 'one');
        $this->studentSend(self::TG_BASE + 3, 'two');
        $this->studentSend(self::TG_BASE + 3, 'three');
        $this->assertCount(1, $this->outboxRows('student_report', 9500003));

        // A different time bucket allows a new notification.
        $_ENV['BOT_REPORT_NOTIFY_WINDOW'] = '7';
        $this->studentSend(self::TG_BASE + 3, 'four');
        $this->assertCount(2, $this->outboxRows('student_report', 9500003));
    }

    public function testClaimIsExclusiveAndReportSent(): void
    {
        $studentId = $this->studentWithSupporter('09995555004', 9500004, self::TG_BASE + 4, self::TG_BASE + 104);

        $svc = new OutboxService();
        $svc->enqueueReplyNotification($studentId, 9500004, 1, '2026-09-30', 'a', []);
        $svc->enqueueReplyNotification($studentId, 9500004, 2, '2026-09-30', 'b', []);

        $claimA = $this->call('POST', '/api/v1/bot/outbox/claim', ['limit' => 1, 'worker_id' => 'A']);
        $claimAData = $this->assertJsonResponse($claimA, 200, ['worker_id', 'items']);
        $this->assertCount(1, $claimAData['data']['items']);
        $idA = $claimAData['data']['items'][0]['id'];

        $claimB = $this->call('POST', '/api/v1/bot/outbox/claim', ['limit' => 1, 'worker_id' => 'B']);
        $claimBData = $this->assertJsonResponse($claimB, 200, ['items']);
        $this->assertCount(1, $claimBData['data']['items']);
        $idB = $claimBData['data']['items'][0]['id'];
        $this->assertNotSame($idA, $idB);

        $report = $this->call('POST', '/api/v1/bot/outbox/report', [
            'worker_id' => 'A',
            'results'   => [['id' => $idA, 'status' => 'sent', 'telegram_message_id' => '555']],
        ]);
        $reportData = $this->assertJsonResponse($report, 200, ['processed', 'results']);
        $this->assertSame('sent', $reportData['data']['results'][0]['result']);

        $row = $this->fetchOne('bot_outbox', ['id' => $idA]);
        $this->assertSame('sent', $row['status']);
        $this->assertSame('555', $row['telegram_message_id']);

        // Reporting the same item again from the same worker is ignored.
        $again = $this->call('POST', '/api/v1/bot/outbox/report', [
            'worker_id' => 'A',
            'results'   => [['id' => $idA, 'status' => 'sent']],
        ]);
        $againData = $this->assertJsonResponse($again, 200);
        $this->assertSame('ignored', $againData['data']['results'][0]['result']);
    }

    public function testFailedReportRetriesThenFails(): void
    {
        $studentId = $this->studentWithSupporter('09995555005', 9500005, self::TG_BASE + 5, self::TG_BASE + 105);
        $_ENV['BOT_OUTBOX_MAX_ATTEMPTS'] = '2';
        $_ENV['BOT_OUTBOX_RETRY_BACKOFF'] = '0';

        $svc = new OutboxService();
        $svc->enqueueReplyNotification($studentId, 9500005, 1, '2026-09-30', 'a', []);

        $claim1 = $this->call('POST', '/api/v1/bot/outbox/claim', ['limit' => 1, 'worker_id' => 'W']);
        $id = $this->assertJsonResponse($claim1, 200)['data']['items'][0]['id'];

        $report1 = $this->call('POST', '/api/v1/bot/outbox/report', [
            'worker_id' => 'W',
            'results'   => [['id' => $id, 'status' => 'failed', 'error' => 'timeout']],
        ]);
        $this->assertSame('retry', $this->assertJsonResponse($report1, 200)['data']['results'][0]['result']);
        $this->assertSame('pending', $this->fetchOne('bot_outbox', ['id' => $id])['status']);

        $claim2 = $this->call('POST', '/api/v1/bot/outbox/claim', ['limit' => 1, 'worker_id' => 'W']);
        $id2 = $this->assertJsonResponse($claim2, 200)['data']['items'][0]['id'];
        $this->assertSame($id, $id2);

        $report2 = $this->call('POST', '/api/v1/bot/outbox/report', [
            'worker_id' => 'W',
            'results'   => [['id' => $id, 'status' => 'failed', 'error' => 'timeout']],
        ]);
        $this->assertSame('failed', $this->assertJsonResponse($report2, 200)['data']['results'][0]['result']);
        $this->assertSame('failed', $this->fetchOne('bot_outbox', ['id' => $id])['status']);
    }

    public function testBlockedReportMarksLinkAndSkipsFutureSends(): void
    {
        $this->studentWithSupporter('09995555006', 9500006, self::TG_BASE + 6, self::TG_BASE + 106);

        $this->studentSend(self::TG_BASE + 6, 'g1');
        $claim = $this->call('POST', '/api/v1/bot/outbox/claim', ['limit' => 1, 'worker_id' => 'W']);
        $id = $this->assertJsonResponse($claim, 200)['data']['items'][0]['id'];

        $report = $this->call('POST', '/api/v1/bot/outbox/report', [
            'worker_id' => 'W',
            'results'   => [['id' => $id, 'status' => 'blocked']],
        ]);
        $this->assertSame('blocked', $this->assertJsonResponse($report, 200)['data']['results'][0]['result']);

        $this->assertSame('skipped', $this->fetchOne('bot_outbox', ['id' => $id])['status']);
        $link = $this->fetchOne('telegram_links', ['role' => 'supporter', 'account_id' => 9500006]);
        $this->assertSame(1, (int) $link['is_blocked']);

        // Future messages produce no new pending notification (link blocked).
        $_ENV['BOT_REPORT_NOTIFY_WINDOW'] = '7';
        $this->studentSend(self::TG_BASE + 6, 'g2');
        $pending = $this->db->query(
            "SELECT COUNT(*) FROM bot_outbox WHERE recipient_account_id = 9500006 AND status = 'pending'"
        )->fetchColumn();
        $this->assertSame(0, (int) $pending);
    }

    public function testStaleLockIsReclaimed(): void
    {
        $studentId = $this->studentWithSupporter('09995555007', 9500007, self::TG_BASE + 7, self::TG_BASE + 107);
        $_ENV['BOT_OUTBOX_LOCK_TTL'] = '1';

        $svc = new OutboxService();
        $svc->enqueueReplyNotification($studentId, 9500007, 1, '2026-09-30', 'a', []);

        $claimA = $this->call('POST', '/api/v1/bot/outbox/claim', ['limit' => 1, 'worker_id' => 'A']);
        $id = $this->assertJsonResponse($claimA, 200)['data']['items'][0]['id'];

        // Simulate a dead worker: lock older than the TTL.
        $this->db->exec("UPDATE bot_outbox SET locked_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 SECOND) WHERE id = {$id}");

        $claimB = $this->call('POST', '/api/v1/bot/outbox/claim', ['limit' => 1, 'worker_id' => 'B']);
        $items = $this->assertJsonResponse($claimB, 200)['data']['items'];
        $this->assertCount(1, $items);
        $this->assertSame($id, $items[0]['id']);

        $row = $this->fetchOne('bot_outbox', ['id' => $id]);
        $this->assertSame('processing', $row['status']);
        $this->assertSame('B', $row['locked_by']);
    }
}
