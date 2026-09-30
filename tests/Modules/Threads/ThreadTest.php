<?php

namespace Tests\Modules\Threads;

use App\Modules\Assignments\Assignment;
use App\Modules\Bot\IranDay;
use App\Modules\Bot\TelegramLink;
use App\Modules\Threads\Message;
use App\Modules\Threads\Thread;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class ThreadTest extends TestCase
{
    private const TG_BASE = 870000000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id BETWEEN 870000000 AND 870999999");
        $this->db->exec("DELETE FROM users WHERE username LIKE '09996666%'");
        $this->db->exec("DELETE FROM students WHERE phone LIKE '09996666%'");
        $this->db->exec("DELETE FROM student_supporter_assignments WHERE supporter_id BETWEEN 9600000 AND 9600999");
        $this->db->exec("DELETE FROM supporters WHERE id BETWEEN 9600000 AND 9600999");
    }

    private function seedSupporter(int $id, string $field = 'ریاضی', int $grade = 12): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO supporters (id, name, grade, field, phone, is_active) VALUES (?, ?, ?, ?, NULL, 1)'
        );
        $stmt->execute([$id, 'Supporter ' . $id, $grade, $field]);
    }

    private function assign(int $studentId, int $supporterId): void
    {
        Assignment::insert($studentId, $supporterId, null, 'test');
    }

    private function link(int $telegramUserId, string $role, int $accountId): void
    {
        TelegramLink::create($telegramUserId, $telegramUserId, $role, $accountId);
    }

    private function call(string $method, string $uri, int $telegramUserId, string $role, array $body = []): ResponseInterface
    {
        return $this->handleRequest($this->jsonRequest($method, $uri, $body, [
            'X-Bot-Key'           => 'test-bot-key',
            'X-Bot-Role'          => $role,
            'X-Telegram-User-Id'  => (string) $telegramUserId,
            'X-Telegram-Chat-Id'  => (string) $telegramUserId,
        ]));
    }

    private function yesterday(): string
    {
        return (new DateTimeImmutable('yesterday', IranDay::timezone()))->format('Y-m-d');
    }

    public function testStudentSendsMessageAndThreadAppears(): void
    {
        $studentId = $this->seedStudent(['phone' => '09996666001', 'name' => 'Thread Student']);
        $this->seedSupporter(9600001);
        $this->assign($studentId, 9600001);
        $this->link(self::TG_BASE + 1, 'student', $studentId);

        $send = $this->call('POST', '/api/v1/bot/threads/messages', self::TG_BASE + 1, 'student', [
            'text'        => 'سلام گزارش امروز',
            'attachments' => [
                ['kind' => 'photo', 'tg_file_id' => 'FILE_1', 'file_name' => 'a.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 123],
            ],
        ]);
        $sendData = $this->assertJsonResponse($send, 201, ['id', 'thread_id', 'day', 'attachments']);
        $this->assertSame(IranDay::today(), $sendData['data']['day']);
        $this->assertCount(1, $sendData['data']['attachments']);
        $threadId = $sendData['data']['thread_id'];

        // Second message same day reuses the same thread.
        $second = $this->call('POST', '/api/v1/bot/threads/messages', self::TG_BASE + 1, 'student', ['text' => 'پیام دوم']);
        $secondData = $this->assertJsonResponse($second, 201);
        $this->assertSame($threadId, $secondData['data']['thread_id']);

        $day = $this->call('GET', '/api/v1/bot/threads/day', self::TG_BASE + 1, 'student');
        $dayData = $this->assertJsonResponse($day, 200, ['messages', 'report_submitted', 'day_jalali']);
        $this->assertTrue($dayData['data']['report_submitted']);
        $this->assertSame(2, $dayData['data']['pagination']['total']);
    }

    public function testStudentWithoutSupporterIsRejected(): void
    {
        $studentId = $this->seedStudent(['phone' => '09996666002', 'name' => 'No Supporter']);
        $this->link(self::TG_BASE + 2, 'student', $studentId);

        $response = $this->call('POST', '/api/v1/bot/threads/messages', self::TG_BASE + 2, 'student', ['text' => 'hi']);

        $this->assertJsonResponse($response, 409, null, 'NO_SUPPORTER_ASSIGNED');
    }

    public function testWeeklyStatusMarksTodaySentAndNeverFutureMissed(): void
    {
        $studentId = $this->seedStudent(['phone' => '09996666003', 'name' => 'Weekly Student']);
        $this->seedSupporter(9600003);
        $this->assign($studentId, 9600003);
        $this->link(self::TG_BASE + 3, 'student', $studentId);

        $this->call('POST', '/api/v1/bot/threads/messages', self::TG_BASE + 3, 'student', ['text' => 'report']);

        $response = $this->call('GET', '/api/v1/bot/threads/weekly', self::TG_BASE + 3, 'student');
        $data = $this->assertJsonResponse($response, 200, ['week_start', 'days']);
        $days = $data['data']['days'];
        $this->assertCount(7, $days);

        $today = IranDay::today();
        foreach ($days as $entry) {
            if ($entry['day'] > $today) {
                $this->assertSame('pending', $entry['state'], 'Future day must never be missed');
            }
            if ($entry['day'] === $today) {
                $this->assertSame('sent', $entry['state']);
                $this->assertTrue($entry['report_submitted']);
            }
            if ($entry['day'] < $today) {
                $this->assertContains($entry['state'], ['sent', 'missed']);
            }
        }
    }

    public function testInboxUnreadAndMarkRead(): void
    {
        $studentId = $this->seedStudent(['phone' => '09996666004', 'name' => 'Inbox Student']);
        $this->seedSupporter(9600004);
        $this->assign($studentId, 9600004);
        $this->link(self::TG_BASE + 4, 'student', $studentId);
        $this->link(self::TG_BASE + 104, 'supporter', 9600004);

        $this->call('POST', '/api/v1/bot/threads/messages', self::TG_BASE + 4, 'student', ['text' => 'one']);
        $this->call('POST', '/api/v1/bot/threads/messages', self::TG_BASE + 4, 'student', ['text' => 'two']);

        $inbox = $this->call('GET', '/api/v1/bot/supporter/inbox', self::TG_BASE + 104, 'supporter');
        $inboxData = $this->assertJsonResponse($inbox, 200, ['total_students', 'students']);
        $row = $this->findStudentRow($inboxData['data']['students'], $studentId);
        $this->assertNotNull($row);
        $this->assertSame(2, $row['unread_count']);

        $unread = $this->call('GET', '/api/v1/bot/supporter/students/' . $studentId . '/unread', self::TG_BASE + 104, 'supporter');
        $unreadData = $this->assertJsonResponse($unread, 200, ['unread_count', 'messages']);
        $this->assertSame(2, $unreadData['data']['unread_count']);

        $read = $this->call('POST', '/api/v1/bot/threads/read', self::TG_BASE + 104, 'supporter', ['student_id' => $studentId]);
        $readData = $this->assertJsonResponse($read, 200, ['marked']);
        $this->assertSame(2, $readData['data']['marked']);

        $inboxAfter = $this->call('GET', '/api/v1/bot/supporter/inbox', self::TG_BASE + 104, 'supporter');
        $inboxAfterData = $this->assertJsonResponse($inboxAfter, 200);
        $this->assertNull($this->findStudentRow($inboxAfterData['data']['students'], $studentId));
    }

    public function testSupporterReplyDefaultsToLatestUnreadDay(): void
    {
        $studentId = $this->seedStudent(['phone' => '09996666005', 'name' => 'Old Unread']);
        $this->seedSupporter(9600005);
        $this->assign($studentId, 9600005);
        $this->link(self::TG_BASE + 105, 'supporter', 9600005);

        $yesterday = $this->yesterday();
        $threadId = Thread::ensure($studentId, $yesterday, 9600005);
        Message::create($threadId, $studentId, 'student', $studentId, 'old message', null, false, true, false);

        $reply = $this->call('POST', '/api/v1/bot/supporter/reply', self::TG_BASE + 105, 'supporter', [
            'student_id' => $studentId,
            'text'       => 'پاسخ به پیام قدیمی',
        ]);
        $replyData = $this->assertJsonResponse($reply, 201, ['day', 'sender_role']);

        $this->assertSame($yesterday, $replyData['data']['day']);
        $this->assertSame('supporter', $replyData['data']['sender_role']);
    }

    public function testSupporterReplyIsUnreadForStudentAndCanBeMarkedRead(): void
    {
        $studentId = $this->seedStudent(['phone' => '09996666006', 'name' => 'Reply Unread']);
        $this->seedSupporter(9600006);
        $this->assign($studentId, 9600006);
        $this->link(self::TG_BASE + 6, 'student', $studentId);
        $this->link(self::TG_BASE + 106, 'supporter', 9600006);

        $this->call('POST', '/api/v1/bot/threads/messages', self::TG_BASE + 6, 'student', ['text' => 'report']);
        $this->call('POST', '/api/v1/bot/supporter/reply', self::TG_BASE + 106, 'supporter', [
            'student_id' => $studentId,
            'text'       => 'آفرین',
        ]);

        $day = $this->call('GET', '/api/v1/bot/threads/day', self::TG_BASE + 6, 'student');
        $dayData = $this->assertJsonResponse($day, 200, ['unread_replies']);
        $this->assertGreaterThanOrEqual(1, $dayData['data']['unread_replies']);

        $read = $this->call('POST', '/api/v1/bot/threads/read', self::TG_BASE + 6, 'student', ['day' => IranDay::today()]);
        $readData = $this->assertJsonResponse($read, 200, ['marked']);
        $this->assertGreaterThanOrEqual(1, $readData['data']['marked']);
    }

    public function testAccessControlForOtherStudent(): void
    {
        $studentA = $this->seedStudent(['phone' => '09996666007', 'name' => 'Student A']);
        $studentB = $this->seedStudent(['phone' => '09996666008', 'name' => 'Student B']);
        $this->seedSupporter(9600007);
        $this->assign($studentA, 9600007);
        $this->assign($studentB, 9600007);
        $this->link(self::TG_BASE + 7, 'student', $studentA);
        $this->link(self::TG_BASE + 107, 'supporter', 9600007);

        // Student A cannot request Student B's thread.
        $response = $this->call('GET', '/api/v1/bot/threads/day?student_id=' . $studentB, self::TG_BASE + 7, 'student');
        $this->assertJsonResponse($response, 403, null, 'FORBIDDEN');

        // An unrelated supporter cannot read the student's unread messages.
        $this->seedSupporter(9600008);
        $this->link(self::TG_BASE + 108, 'supporter', 9600008);
        $unrelated = $this->call('GET', '/api/v1/bot/supporter/students/' . $studentA . '/unread', self::TG_BASE + 108, 'supporter');
        $this->assertJsonResponse($unrelated, 403, null, 'FORBIDDEN');
    }

    public function testAlbumWithMultipleAttachments(): void
    {
        $studentId = $this->seedStudent(['phone' => '09996666009', 'name' => 'Album Student']);
        $this->seedSupporter(9600009);
        $this->assign($studentId, 9600009);
        $this->link(self::TG_BASE + 9, 'student', $studentId);

        $response = $this->call('POST', '/api/v1/bot/threads/messages', self::TG_BASE + 9, 'student', [
            'media_group_id' => 'GROUP-1',
            'attachments'    => [
                ['kind' => 'photo', 'tg_file_id' => 'P1'],
                ['kind' => 'photo', 'tg_file_id' => 'P2'],
                ['kind' => 'document', 'tg_file_id' => 'D1', 'file_name' => 'x.pdf'],
            ],
        ]);

        $data = $this->assertJsonResponse($response, 201, ['attachments']);
        $this->assertCount(3, $data['data']['attachments']);
        $this->assertSame('GROUP-1', $data['data']['media_group_id']);
    }

    private function findStudentRow(array $rows, int $studentId): ?array
    {
        foreach ($rows as $row) {
            if ((int) $row['student_id'] === $studentId) {
                return $row;
            }
        }

        return null;
    }
}
