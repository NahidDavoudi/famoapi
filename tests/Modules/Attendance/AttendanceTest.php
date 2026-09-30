<?php

namespace Tests\Modules\Attendance;

use App\Modules\Bot\IranDay;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    private const DATE = '2031-06-15';
    private const FIELD = 'ATT_TST_FIELD';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $this->db->exec("DELETE FROM daily_attendance WHERE attendance_date = '" . self::DATE . "'");
        $this->db->exec("DELETE FROM daily_attendance WHERE guest_name LIKE 'ATT_TST_%'");
        $this->db->exec(
            "DELETE FROM users WHERE role = 'student' AND linked_id IN "
            . "(SELECT id FROM students WHERE field = '" . self::FIELD . "')"
        );
        $this->db->exec("DELETE FROM students WHERE field = '" . self::FIELD . "'");
    }

    private function seedAttendanceStudent(string $name = 'Att Student'): int
    {
        return $this->seedStudent(['field' => self::FIELD, 'name' => $name]);
    }

    private function supporterWrite(string $method, string $uri, array $body = []): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->supporterRequest($method, $uri, $body, ['Origin' => 'http://localhost']);
    }

    public function testListDayReturnsActiveStudents(): void
    {
        $studentId = $this->seedAttendanceStudent('Active One');

        $response = $this->handleRequest(
            $this->supporterRequest('GET', '/api/v1/attendance?date=' . self::DATE . '&field=' . self::FIELD)
        );
        $data = $this->assertJsonResponse($response, 200, ['date', 'date_jalali', 'field', 'entries']);

        $this->assertSame(self::DATE, $data['data']['date']);
        $this->assertSame(IranDay::jalali(self::DATE), $data['data']['date_jalali']);
        $this->assertSame(self::FIELD, $data['data']['field']);
        $this->assertCount(1, $data['data']['entries']);

        $entry = $data['data']['entries'][0];
        $this->assertSame($studentId, $entry['student_id']);
        $this->assertSame('Active One', $entry['name']);
        $this->assertFalse($entry['is_guest']);
        $this->assertNull($entry['id']);
        $this->assertNull($entry['status']);
    }

    public function testListDayExcludesInactiveStudents(): void
    {
        $studentId = $this->seedAttendanceStudent('Inactive One');
        $this->db->exec("UPDATE students SET is_active = 0 WHERE id = {$studentId}");

        $response = $this->handleRequest(
            $this->supporterRequest('GET', '/api/v1/attendance?date=' . self::DATE . '&field=' . self::FIELD)
        );
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(0, $data['data']['entries']);
    }

    public function testUpsertMarksPresent(): void
    {
        $studentId = $this->seedAttendanceStudent();

        $response = $this->handleRequest(
            $this->supporterWrite('PUT', '/api/v1/attendance/entries', [
                'date' => self::DATE,
                'student_id' => $studentId,
            ])
        );
        $data = $this->assertJsonResponse($response, 200);

        $this->assertSame($studentId, $data['data']['student_id']);
        $this->assertSame('present', $data['data']['status']);
        $this->assertNotNull($data['data']['id']);

        $row = $this->fetchOne('daily_attendance', ['id' => $data['data']['id']]);
        $this->assertSame('present', $row['status']);
        $this->assertNull($row['removed_at']);
    }

    public function testUpsertWithRemovedHidesRowFromList(): void
    {
        $studentId = $this->seedAttendanceStudent();

        $this->handleRequest($this->supporterWrite('PUT', '/api/v1/attendance/entries', [
            'date' => self::DATE,
            'student_id' => $studentId,
            'status' => 'present',
        ]));

        $removed = $this->handleRequest($this->supporterWrite('PUT', '/api/v1/attendance/entries', [
            'date' => self::DATE,
            'student_id' => $studentId,
            'removed' => true,
        ]));
        $this->assertJsonResponse($removed, 200);

        $row = $this->fetchOne('daily_attendance', ['attendance_date' => self::DATE, 'student_id' => $studentId]);
        $this->assertNotNull($row['removed_at']);

        $response = $this->handleRequest(
            $this->supporterRequest('GET', '/api/v1/attendance?date=' . self::DATE . '&field=' . self::FIELD)
        );
        $data = $this->assertJsonResponse($response, 200);
        $this->assertCount(1, $data['data']['entries']);
        $this->assertNull($data['data']['entries'][0]['id']);
        $this->assertNull($data['data']['entries'][0]['status']);
    }

    public function testSetTimeWithNow(): void
    {
        $studentId = $this->seedAttendanceStudent();
        $entryId = $this->upsertPresent($studentId);

        $response = $this->handleRequest(
            $this->supporterWrite('PATCH', "/api/v1/attendance/{$entryId}/times", [
                'field' => 'arrived_at',
                'value' => 'now',
            ])
        );
        $data = $this->assertJsonResponse($response, 200);

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            (string) $data['data']['arrived_at']
        );

        $row = $this->fetchOne('daily_attendance', ['id' => $entryId]);
        $this->assertNotNull($row['arrived_at']);
    }

    public function testSetTimeWithNullClearsColumn(): void
    {
        $studentId = $this->seedAttendanceStudent();
        $entryId = $this->upsertPresent($studentId);

        $this->handleRequest($this->supporterWrite('PATCH', "/api/v1/attendance/{$entryId}/times", [
            'field' => 'arrived_at',
            'value' => 'now',
        ]));

        $response = $this->handleRequest(
            $this->supporterWrite('PATCH', "/api/v1/attendance/{$entryId}/times", [
                'field' => 'arrived_at',
                'value' => null,
            ])
        );
        $data = $this->assertJsonResponse($response, 200);
        $this->assertNull($data['data']['arrived_at']);

        $row = $this->fetchOne('daily_attendance', ['id' => $entryId]);
        $this->assertNull($row['arrived_at']);
    }

    public function testSetTimeWithInvalidFieldReturns422(): void
    {
        $studentId = $this->seedAttendanceStudent();
        $entryId = $this->upsertPresent($studentId);

        $response = $this->handleRequest(
            $this->supporterWrite('PATCH', "/api/v1/attendance/{$entryId}/times", [
                'field' => 'created_at',
                'value' => 'now',
            ])
        );
        $this->assertJsonResponse($response, 422, null, 'VALIDATION_ERROR');
    }

    public function testSetTimeUnknownIdReturns404(): void
    {
        $response = $this->handleRequest(
            $this->supporterWrite('PATCH', '/api/v1/attendance/999999999/times', [
                'field' => 'arrived_at',
                'value' => 'now',
            ])
        );
        $this->assertJsonResponse($response, 404, null, 'NOT_FOUND');
    }

    public function testAddGuestAppearsInList(): void
    {
        $response = $this->handleRequest(
            $this->supporterWrite('POST', '/api/v1/attendance/guests', [
                'date' => self::DATE,
                'guest_name' => 'ATT_TST_Guest',
                'field' => self::FIELD,
            ])
        );
        $data = $this->assertJsonResponse($response, 201);

        $this->assertTrue($data['data']['is_guest']);
        $this->assertNull($data['data']['student_id']);
        $this->assertSame('ATT_TST_Guest', $data['data']['guest_name']);
        $this->assertSame('present', $data['data']['status']);

        $list = $this->handleRequest(
            $this->supporterRequest('GET', '/api/v1/attendance?date=' . self::DATE . '&field=' . self::FIELD)
        );
        $listData = $this->assertJsonResponse($list, 200);
        $this->assertCount(1, $listData['data']['entries']);
        $this->assertTrue($listData['data']['entries'][0]['is_guest']);
        $this->assertSame('ATT_TST_Guest', $listData['data']['entries'][0]['guest_name']);
    }

    public function testDuplicateClientUuidIsIdempotent(): void
    {
        $studentId = $this->seedAttendanceStudent();
        $uuid = '11111111-1111-1111-1111-111111111111';

        $first = $this->handleRequest($this->supporterWrite('PUT', '/api/v1/attendance/entries', [
            'date' => self::DATE,
            'student_id' => $studentId,
            'client_uuid' => $uuid,
        ]));
        $firstData = $this->assertJsonResponse($first, 200);

        $second = $this->handleRequest($this->supporterWrite('PUT', '/api/v1/attendance/entries', [
            'date' => self::DATE,
            'student_id' => $studentId,
            'client_uuid' => $uuid,
        ]));
        $secondData = $this->assertJsonResponse($second, 200);

        $this->assertSame($firstData['data']['id'], $secondData['data']['id']);

        $count = (int) $this->db->query(
            "SELECT COUNT(*) FROM daily_attendance WHERE attendance_date = '" . self::DATE
            . "' AND student_id = {$studentId}"
        )->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testPrintOmitsTimeKeys(): void
    {
        $studentId = $this->seedAttendanceStudent();
        $entryId = $this->upsertPresent($studentId);
        $this->handleRequest($this->supporterWrite('PATCH', "/api/v1/attendance/{$entryId}/times", [
            'field' => 'departed_at',
            'value' => 'now',
        ]));

        $response = $this->handleRequest(
            $this->supporterRequest('GET', '/api/v1/attendance/print?date=' . self::DATE . '&field=' . self::FIELD)
        );
        $data = $this->assertJsonResponse($response, 200, ['date', 'date_jalali', 'field', 'entries']);
        $this->assertCount(1, $data['data']['entries']);

        $entry = $data['data']['entries'][0];
        foreach (['arrived_at', 'exam_started_at', 'exam_ended_at', 'departed_at'] as $key) {
            $this->assertArrayNotHasKey($key, $entry);
        }
        $this->assertArrayHasKey('created_at', $entry);
    }

    public function testUpsertRequiresStudentId(): void
    {
        $response = $this->handleRequest(
            $this->supporterWrite('PUT', '/api/v1/attendance/entries', ['date' => self::DATE])
        );
        $this->assertJsonResponse($response, 422, null, 'VALIDATION_ERROR');
    }

    public function testSupporterCanReadAndAnonymousCannot(): void
    {
        $allowed = $this->handleRequest(
            $this->supporterRequest('GET', '/api/v1/attendance?date=' . self::DATE)
        );
        $this->assertJsonResponse($allowed, 200);

        $anonymous = $this->handleRequest(
            $this->createRequest('GET', '/api/v1/attendance?date=' . self::DATE)
        );
        $this->assertJsonResponse($anonymous, 401, null, 'UNAUTHORIZED');
    }

    private function upsertPresent(int $studentId): int
    {
        $response = $this->handleRequest($this->supporterWrite('PUT', '/api/v1/attendance/entries', [
            'date' => self::DATE,
            'student_id' => $studentId,
        ]));
        $data = $this->assertJsonResponse($response, 200);

        return (int) $data['data']['id'];
    }
}
