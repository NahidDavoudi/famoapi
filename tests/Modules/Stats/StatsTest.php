<?php

namespace Tests\Modules\Stats;

use App\Modules\Assignments\Assignment;
use App\Modules\Bot\IranDay;
use App\Modules\Threads\Thread;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class StatsTest extends TestCase
{
    private ?string $originalContentReaders = null;

    private string $d1;
    private string $d2;
    private string $d3;

    private int $s1;
    private int $s2;
    private int $s3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalContentReaders = $_ENV['ADMIN_CONTENT_READER_IDS'] ?? null;
        unset($_ENV['ADMIN_CONTENT_READER_IDS']);

        $this->db->exec("DELETE FROM student_supporter_assignments WHERE supporter_id BETWEEN 9300000 AND 9300999");
        $this->db->exec("DELETE FROM student_supporter_assignments WHERE student_id IN (SELECT id FROM students WHERE phone LIKE '09993333%')");
        $this->db->exec("DELETE FROM supporters WHERE id BETWEEN 9300000 AND 9300999");
        $this->db->exec("DELETE FROM users WHERE username LIKE '09993333%'");
        $this->db->exec("DELETE FROM students WHERE phone LIKE '09993333%'");

        $this->d3 = IranDay::today();
        $this->d2 = IranDay::addDays($this->d3, -1);
        $this->d1 = IranDay::addDays($this->d3, -2);

        $this->seedSupporter(9300001, 'ریاضی');
        $this->seedSupporter(9300002, 'انسانی');

        $this->s1 = $this->seedStudent(['phone' => '09993333001', 'name' => 'Stats S1', 'field' => 'ریاضی', 'grade' => 12]);
        $this->s2 = $this->seedStudent(['phone' => '09993333002', 'name' => 'Stats S2', 'field' => 'تجربی', 'grade' => 12]);
        $this->s3 = $this->seedStudent(['phone' => '09993333003', 'name' => 'Stats S3', 'field' => 'انسانی', 'grade' => 12]);

        Assignment::insert($this->s1, 9300001, null, 'test');
        Assignment::insert($this->s2, 9300001, null, 'test');
        Assignment::insert($this->s3, 9300002, null, 'test');

        // S1: D1 report + reply (30 min), D2 report (no reply), D3 report.
        $t1 = Thread::ensure($this->s1, $this->d1, 9300001);
        $this->insertMessage($t1, $this->s1, 'student', $this->s1, 's1 d1', $this->d1 . ' 08:00:00', 1);
        $this->insertMessage($t1, $this->s1, 'supporter', 9300001, 's1 d1 reply', $this->d1 . ' 08:30:00');

        $t2 = Thread::ensure($this->s1, $this->d2, 9300001);
        $this->insertMessage($t2, $this->s1, 'student', $this->s1, 's1 d2', $this->d2 . ' 09:00:00');

        $t3 = Thread::ensure($this->s1, $this->d3, 9300001);
        $this->insertMessage($t3, $this->s1, 'student', $this->s1, 's1 d3', $this->d3 . ' 07:00:00');

        // S2: D1 report, no reply.
        $t4 = Thread::ensure($this->s2, $this->d1, 9300001);
        $this->insertMessage($t4, $this->s2, 'student', $this->s2, 's2 d1', $this->d1 . ' 10:00:00');
    }

    protected function tearDown(): void
    {
        if ($this->originalContentReaders === null) {
            unset($_ENV['ADMIN_CONTENT_READER_IDS']);
        } else {
            $_ENV['ADMIN_CONTENT_READER_IDS'] = $this->originalContentReaders;
        }

        parent::tearDown();
    }

    private function seedSupporter(int $id, string $field): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO supporters (id, name, grade, field, phone, is_active) VALUES (?, ?, 12, ?, NULL, 1)'
        );
        $stmt->execute([$id, 'Supporter ' . $id, $field]);
    }

    private function insertMessage(
        int $threadId,
        int $studentId,
        string $role,
        int $senderAccountId,
        string $body,
        string $createdAtUtc,
        int $readBySupporter = 0
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO report_messages
                (thread_id, student_id, sender_role, sender_account_id, body, is_broadcast, read_by_student, read_by_supporter, created_at)
             VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?)'
        );
        $stmt->execute([$threadId, $studentId, $role, $senderAccountId, $body, $readBySupporter, $createdAtUtc]);
    }

    private function adminGet(string $uri): ResponseInterface
    {
        return $this->handleRequest($this->adminRequest('GET', $uri));
    }

    private function findItem(array $items, string $key, $value): ?array
    {
        foreach ($items as $item) {
            if ($item[$key] === $value) {
                return $item;
            }
        }

        return null;
    }

    public function testReportsOverview(): void
    {
        $response = $this->adminGet('/api/v1/admin/stats/reports/overview?from=' . $this->d1 . '&to=' . $this->d3);
        $data = $this->assertJsonResponse($response, 200, ['from', 'to', 'active_students', 'overall_rate', 'days']);

        $this->assertSame(3, $data['data']['days_count']);
        $this->assertGreaterThanOrEqual(3, $data['data']['active_students']);

        $d1 = $this->findItem($data['data']['days'], 'day', $this->d1);
        $this->assertNotNull($d1);
        $this->assertGreaterThanOrEqual(2, $d1['report_count']);
    }

    public function testReportsByMajor(): void
    {
        $response = $this->adminGet('/api/v1/admin/stats/reports/by-major?from=' . $this->d1 . '&to=' . $this->d3);
        $data = $this->assertJsonResponse($response, 200, ['items']);

        $riazi = $this->findItem($data['data']['items'], 'field', 'ریاضی');
        $this->assertNotNull($riazi);
        $this->assertGreaterThanOrEqual(3, $riazi['report_count']);

        $tajrobi = $this->findItem($data['data']['items'], 'field', 'تجربی');
        $this->assertNotNull($tajrobi);
        $this->assertGreaterThanOrEqual(1, $tajrobi['report_count']);
    }

    public function testReportsBySupporter(): void
    {
        $response = $this->adminGet('/api/v1/admin/stats/reports/by-supporter?from=' . $this->d1 . '&to=' . $this->d3);
        $data = $this->assertJsonResponse($response, 200, ['items']);

        $p1 = $this->findItem($data['data']['items'], 'supporter_id', 9300001);
        $this->assertNotNull($p1);
        $this->assertSame(2, $p1['assigned_students']);
        $this->assertSame(4, $p1['report_count']);
        $this->assertSame(0.6667, $p1['rate']);
    }

    public function testStudentsWithNoReport(): void
    {
        $response = $this->adminGet('/api/v1/admin/stats/students/no-report?days=3&search=Stats%20S3');
        $data = $this->assertJsonResponse($response, 200, ['students']);
        $this->assertNotNull($data['pagination']);

        $ids = array_column($data['data']['students'], 'id');
        $this->assertContains($this->s3, $ids);
        $this->assertNotContains($this->s1, $ids);
        $this->assertNotContains($this->s2, $ids);
    }

    public function testSupporterPerformance(): void
    {
        $response = $this->adminGet('/api/v1/admin/stats/supporters/performance?from=' . $this->d1 . '&to=' . $this->d3);
        $data = $this->assertJsonResponse($response, 200, ['items']);

        $p1 = $this->findItem($data['data']['items'], 'supporter_id', 9300001);
        $this->assertNotNull($p1);
        $this->assertSame(2, $p1['assigned_students']);
        $this->assertSame(4, $p1['threads_with_reports']);
        $this->assertSame(1, $p1['threads_with_reply']);
        $this->assertSame(0.25, $p1['reply_rate']);
        $this->assertSame(3, $p1['unanswered_threads']);
        $this->assertSame($this->d1, $p1['oldest_unanswered_day']);
        $this->assertSame(1800, $p1['avg_first_reply_seconds']);
        $this->assertSame(1800, $p1['median_first_reply_seconds']);
        $this->assertSame(1, $p1['messages_sent']);
        $this->assertSame(0, $p1['broadcasts_sent']);
        $this->assertSame(3, $p1['unread_backlog']);
    }

    public function testStudentHistory(): void
    {
        $response = $this->adminGet('/api/v1/admin/stats/students/' . $this->s1 . '/history?from=' . $this->d1 . '&to=' . $this->d3);
        $data = $this->assertJsonResponse($response, 200, ['student', 'days']);

        $this->assertCount(3, $data['data']['days']);
        $d1 = $this->findItem($data['data']['days'], 'day', $this->d1);
        $this->assertTrue($d1['report_submitted']);
        $this->assertTrue($d1['has_reply']);
        $this->assertSame(1800, $d1['first_reply_seconds']);

        $d2 = $this->findItem($data['data']['days'], 'day', $this->d2);
        $this->assertTrue($d2['report_submitted']);
        $this->assertFalse($d2['has_reply']);
    }

    public function testContentAccessPermissionGate(): void
    {
        $uri = '/api/v1/admin/stats/students/' . $this->s1 . '/thread?day=' . $this->d3;

        // Default: disabled.
        $disabled = $this->adminGet($uri);
        $this->assertJsonResponse($disabled, 403, null, 'CONTENT_ACCESS_DISABLED');

        // Enabled for admin user id 1.
        $_ENV['ADMIN_CONTENT_READER_IDS'] = '1';
        $enabled = $this->adminGet($uri);
        $data = $this->assertJsonResponse($enabled, 200, ['student', 'messages']);

        $this->assertNotEmpty($data['data']['messages']);
        $this->assertSame('s1 d3', $data['data']['messages'][0]['body']);
    }
}
