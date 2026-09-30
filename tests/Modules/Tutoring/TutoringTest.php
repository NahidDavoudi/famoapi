<?php

namespace Tests\Modules\Tutoring;

use App\Core\Auth;
use App\Modules\Bot\IranDay;
use Psr\Http\Message\ServerRequestInterface;
use Tests\TestCase;

class TutoringTest extends TestCase
{
    private const FIELD = 'TUT_TST_FIELD';

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
        $this->db->exec(
            "DELETE s FROM tutoring_sessions s JOIN tutoring_classrooms c ON c.id = s.classroom_id WHERE c.name LIKE 'TUT_TST_%'"
        );
        $this->db->exec(
            "DELETE s FROM tutoring_sessions s JOIN students st ON st.id = s.student_id WHERE st.field = '" . self::FIELD . "'"
        );
        $this->db->exec(
            "DELETE FROM tutoring_teacher_status_log WHERE instructor_id IN (SELECT id FROM instructors WHERE name LIKE 'TUT_TST_%')"
        );
        $this->db->exec("DELETE FROM tutoring_classrooms WHERE name LIKE 'TUT_TST_%'");
        $this->db->exec(
            "DELETE FROM daily_attendance WHERE student_id IN (SELECT id FROM students WHERE field = '" . self::FIELD . "')"
        );
        $this->db->exec(
            "DELETE FROM users WHERE role = 'student' AND linked_id IN (SELECT id FROM students WHERE field = '" . self::FIELD . "')"
        );
        $this->db->exec("DELETE FROM users WHERE username LIKE 'tut_test_%'");
        $this->db->exec("DELETE FROM students WHERE field = '" . self::FIELD . "'");
        $this->db->exec("DELETE FROM instructors WHERE name LIKE 'TUT_TST_%'");
    }

    /**
     * @return array{user_id:int,instructor_id:int}
     */
    private function seedTeacher(): array
    {
        $instructorId = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM instructors')->fetchColumn();
        $stmt = $this->db->prepare(
            'INSERT INTO instructors (id, name, title, display_order) VALUES (:id, :name, :title, 0)'
        );
        $stmt->execute([
            'id' => $instructorId,
            'name' => 'TUT_TST_Teacher ' . uniqid(),
            'title' => 'مدرس',
        ]);

        $userId = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM users')->fetchColumn();
        $stmt = $this->db->prepare(
            'INSERT INTO users (id, username, password_hash, role, linked_id, created_at)
             VALUES (:id, :username, :password_hash, :role, :linked_id, NOW())'
        );
        $stmt->execute([
            'id' => $userId,
            'username' => 'tut_test_' . uniqid(),
            'password_hash' => password_hash('1234', PASSWORD_DEFAULT),
            'role' => 'teacher',
            'linked_id' => $instructorId,
        ]);

        return ['user_id' => $userId, 'instructor_id' => $instructorId];
    }

    private function seedClassroom(int $instructorId, ?string $name = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tutoring_classrooms (name, instructor_id, sort_order, is_active, created_at)
             VALUES (:name, :instructor_id, 0, 1, NOW())'
        );
        $stmt->execute([
            'name' => $name ?? 'TUT_TST_Room ' . uniqid(),
            'instructor_id' => $instructorId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array{user_id:int,instructor_id:int} $teacher
     * @param array<string,mixed> $data
     * @param array<string,mixed> $query
     */
    private function teacherRequest(
        string $method,
        string $uri,
        array $teacher,
        array $data = [],
        array $query = []
    ): ServerRequestInterface {
        $token = Auth::encode([
            'id' => $teacher['user_id'],
            'role' => 'teacher',
            'instructor_id' => $teacher['instructor_id'],
        ]);

        $request = $this->jsonRequest($method, $uri, $data, ['Origin' => 'http://localhost']);
        if (!empty($query)) {
            $request = $request->withQueryParams($query);
        }

        return $request->withCookieParams(['famo_jwt' => $token]);
    }

    public function testMeReturnsClassroomStatusAndOpenSession(): void
    {
        $teacher = $this->seedTeacher();
        $classroomId = $this->seedClassroom($teacher['instructor_id']);
        $studentId = $this->seedStudent(['field' => self::FIELD, 'name' => 'TUT_TST_Me']);

        $status = $this->handleRequest(
            $this->teacherRequest('PUT', '/api/v1/tutoring/status', $teacher, ['status' => 'ready'])
        );
        $this->assertJsonResponse($status, 200);

        $started = $this->handleRequest(
            $this->teacherRequest('POST', '/api/v1/tutoring/sessions', $teacher, ['student_id' => $studentId])
        );
        $startedData = $this->assertJsonResponse($started, 201);

        $response = $this->handleRequest(
            $this->teacherRequest('GET', '/api/v1/tutoring/me', $teacher)
        );
        $data = $this->assertJsonResponse($response, 200, ['classroom', 'status', 'open_session']);

        $this->assertSame($classroomId, $data['data']['classroom']['id']);
        $this->assertArrayHasKey('name', $data['data']['classroom']);
        $this->assertArrayHasKey('sort_order', $data['data']['classroom']);
        $this->assertArrayHasKey('is_active', $data['data']['classroom']);

        $this->assertSame('ready', $data['data']['status']['status']);
        $this->assertSame(IranDay::today(), $data['data']['status']['status_date']);
        $this->assertArrayHasKey('started_at', $data['data']['status']);

        $this->assertSame($startedData['data']['id'], $data['data']['open_session']['id']);
        $this->assertSame($studentId, $data['data']['open_session']['student_id']);
    }

    public function testMeWithoutClassroomReturns404(): void
    {
        $teacher = $this->seedTeacher();

        $response = $this->handleRequest(
            $this->teacherRequest('GET', '/api/v1/tutoring/me', $teacher)
        );

        $this->assertJsonResponse($response, 404, null, 'NOT_FOUND');
    }

    public function testMeWithNullStatusAndOpenSession(): void
    {
        $teacher = $this->seedTeacher();
        $this->seedClassroom($teacher['instructor_id']);

        $response = $this->handleRequest(
            $this->teacherRequest('GET', '/api/v1/tutoring/me', $teacher)
        );
        $data = $this->assertJsonResponse($response, 200, ['classroom', 'status', 'open_session']);

        $this->assertNull($data['data']['status']);
        $this->assertNull($data['data']['open_session']);
    }

    public function testMeFallsBackToLinkedUserForInstructorId(): void
    {
        $teacher = $this->seedTeacher();
        $classroomId = $this->seedClassroom($teacher['instructor_id']);

        $token = Auth::encode(['id' => $teacher['user_id'], 'role' => 'teacher']);
        $request = $this->jsonRequest('GET', '/api/v1/tutoring/me')
            ->withCookieParams(['famo_jwt' => $token]);

        $response = $this->handleRequest($request);
        $data = $this->assertJsonResponse($response, 200, ['classroom']);

        $this->assertSame($classroomId, $data['data']['classroom']['id']);
    }

    public function testTeacherWithoutInstructorIdentityIsForbidden(): void
    {
        $userId = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM users')->fetchColumn();
        $stmt = $this->db->prepare(
            'INSERT INTO users (id, username, password_hash, role, linked_id, created_at)
             VALUES (:id, :username, :password_hash, :role, NULL, NOW())'
        );
        $stmt->execute([
            'id' => $userId,
            'username' => 'tut_test_lonely',
            'password_hash' => password_hash('1234', PASSWORD_DEFAULT),
            'role' => 'teacher',
        ]);

        $token = Auth::encode(['id' => $userId, 'role' => 'teacher']);
        $request = $this->jsonRequest('GET', '/api/v1/tutoring/me')
            ->withCookieParams(['famo_jwt' => $token]);

        $response = $this->handleRequest($request);
        $this->assertJsonResponse($response, 403, null, 'FORBIDDEN');
    }

    public function testSetStatusClosesPreviousRow(): void
    {
        $teacher = $this->seedTeacher();

        $first = $this->handleRequest(
            $this->teacherRequest('PUT', '/api/v1/tutoring/status', $teacher, ['status' => 'ready'])
        );
        $firstData = $this->assertJsonResponse($first, 200);
        $firstId = (int) $firstData['data']['id'];

        $second = $this->handleRequest(
            $this->teacherRequest('PUT', '/api/v1/tutoring/status', $teacher, ['status' => 'break'])
        );
        $secondData = $this->assertJsonResponse($second, 200);
        $secondId = (int) $secondData['data']['id'];

        $this->assertSame('break', $secondData['data']['status']);
        $this->assertNotSame($firstId, $secondId);

        $firstRow = $this->fetchOne('tutoring_teacher_status_log', ['id' => $firstId]);
        $secondRow = $this->fetchOne('tutoring_teacher_status_log', ['id' => $secondId]);

        $this->assertNotNull($firstRow['ended_at']);
        $this->assertNull($secondRow['ended_at']);
        $this->assertSame(IranDay::today(), $secondRow['status_date']);
    }

    public function testSetStatusInvalidReturns422(): void
    {
        $teacher = $this->seedTeacher();

        $response = $this->handleRequest(
            $this->teacherRequest('PUT', '/api/v1/tutoring/status', $teacher, ['status' => 'nope'])
        );

        $this->assertJsonResponse($response, 422, null, 'VALIDATION_ERROR');
    }

    public function testStudentsSearchFiltersActiveStudents(): void
    {
        $activeId = $this->seedStudent(['field' => self::FIELD, 'name' => 'TUT_TST_Ali']);
        $inactiveId = $this->seedStudent([
            'field' => self::FIELD,
            'name' => 'TUT_TST_Inactive',
            'phone' => '09120000001',
        ]);
        $this->db->exec("UPDATE students SET is_active = 0 WHERE id = {$inactiveId}");

        $teacher = $this->seedTeacher();

        $response = $this->handleRequest(
            $this->teacherRequest('GET', '/api/v1/tutoring/students', $teacher, [], ['q' => 'TUT_TST'])
        );
        $data = $this->assertJsonResponse($response, 200);

        $this->assertIsArray($data['data']);
        $this->assertNull($data['pagination']);
        $this->assertCount(1, $data['data']);
        $this->assertSame($activeId, $data['data'][0]['id']);
        $this->assertArrayHasKey('name', $data['data'][0]);
        $this->assertArrayHasKey('grade', $data['data'][0]);
        $this->assertArrayHasKey('field', $data['data'][0]);
    }

    public function testStudentsSearchByPhone(): void
    {
        $studentId = $this->seedStudent([
            'field' => self::FIELD,
            'name' => 'TUT_TST_PhoneSearch',
            'phone' => '09120000009',
        ]);
        $teacher = $this->seedTeacher();

        $response = $this->handleRequest(
            $this->teacherRequest('GET', '/api/v1/tutoring/students', $teacher, [], ['q' => '09120000009'])
        );
        $data = $this->assertJsonResponse($response, 200);

        $this->assertCount(1, $data['data']);
        $this->assertSame($studentId, $data['data'][0]['id']);
    }

    public function testStartSessionCreatesOpenSessionAndIsIdempotent(): void
    {
        $teacher = $this->seedTeacher();
        $classroomId = $this->seedClassroom($teacher['instructor_id']);
        $studentId = $this->seedStudent(['field' => self::FIELD, 'name' => 'TUT_TST_Start']);
        $uuid = '22222222-2222-2222-2222-222222222222';

        $first = $this->handleRequest(
            $this->teacherRequest('POST', '/api/v1/tutoring/sessions', $teacher, [
                'student_id' => $studentId,
                'client_uuid' => $uuid,
            ])
        );
        $firstData = $this->assertJsonResponse($first, 201);
        $sessionId = (int) $firstData['data']['id'];

        $this->assertSame($classroomId, $firstData['data']['classroom_id']);
        $this->assertSame($studentId, $firstData['data']['student_id']);
        $this->assertSame(IranDay::today(), $firstData['data']['session_date']);
        $this->assertSame('auto', $firstData['data']['entry_source']);
        $this->assertSame($teacher['user_id'], $firstData['data']['created_by']);
        $this->assertNull($firstData['data']['ended_at']);

        $second = $this->handleRequest(
            $this->teacherRequest('POST', '/api/v1/tutoring/sessions', $teacher, [
                'student_id' => $studentId,
                'client_uuid' => $uuid,
            ])
        );
        $secondData = $this->assertJsonResponse($second, 200);

        $this->assertSame($sessionId, $secondData['data']['id']);

        $count = (int) $this->db->query(
            "SELECT COUNT(*) FROM tutoring_sessions WHERE classroom_id = {$classroomId} AND student_id = {$studentId}"
        )->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testStartSessionLinksPresentAttendance(): void
    {
        $teacher = $this->seedTeacher();
        $this->seedClassroom($teacher['instructor_id']);
        $studentId = $this->seedStudent(['field' => self::FIELD, 'name' => 'TUT_TST_Linked']);

        $today = IranDay::today();
        $stmt = $this->db->prepare(
            'INSERT INTO daily_attendance (attendance_date, student_id, status, created_at, updated_at)
             VALUES (:date, :student_id, :status, NOW(), NOW())'
        );
        $stmt->execute(['date' => $today, 'student_id' => $studentId, 'status' => 'present']);
        $attendanceId = (int) $this->db->lastInsertId();

        $response = $this->handleRequest(
            $this->teacherRequest('POST', '/api/v1/tutoring/sessions', $teacher, ['student_id' => $studentId])
        );
        $data = $this->assertJsonResponse($response, 201);

        $this->assertSame($attendanceId, $data['data']['attendance_id']);
    }

    public function testStartSessionNormalizesEnteredAtToUtc(): void
    {
        $teacher = $this->seedTeacher();
        $this->seedClassroom($teacher['instructor_id']);
        $studentId = $this->seedStudent(['field' => self::FIELD, 'name' => 'TUT_TST_Time']);

        $response = $this->handleRequest(
            $this->teacherRequest('POST', '/api/v1/tutoring/sessions', $teacher, [
                'student_id' => $studentId,
                'entered_at' => '2031-06-15T10:00:00+03:30',
            ])
        );
        $data = $this->assertJsonResponse($response, 201);

        $this->assertSame('2031-06-15 06:30:00', $data['data']['entered_at']);
    }

    public function testSecondOpenSessionForSameStudentIsRejected(): void
    {
        $teacher = $this->seedTeacher();
        $this->seedClassroom($teacher['instructor_id']);
        $studentId = $this->seedStudent(['field' => self::FIELD, 'name' => 'TUT_TST_Duplicate']);

        $first = $this->handleRequest(
            $this->teacherRequest('POST', '/api/v1/tutoring/sessions', $teacher, ['student_id' => $studentId])
        );
        $this->assertJsonResponse($first, 201);

        $second = $this->handleRequest(
            $this->teacherRequest('POST', '/api/v1/tutoring/sessions', $teacher, [
                'student_id' => $studentId,
                'client_uuid' => '33333333-3333-3333-3333-333333333333',
            ])
        );
        $data = $this->assertJsonResponse($second, 409, null, 'CONFLICT');

        $this->assertNotSame('', $data['error']['message']);
    }

    public function testStartSessionRequiresStudentId(): void
    {
        $teacher = $this->seedTeacher();
        $this->seedClassroom($teacher['instructor_id']);

        $response = $this->handleRequest(
            $this->teacherRequest('POST', '/api/v1/tutoring/sessions', $teacher, [])
        );

        $this->assertJsonResponse($response, 422, null, 'VALIDATION_ERROR');
    }

    public function testEndSessionClosesOpenSession(): void
    {
        $teacher = $this->seedTeacher();
        $this->seedClassroom($teacher['instructor_id']);
        $studentId = $this->seedStudent(['field' => self::FIELD, 'name' => 'TUT_TST_End']);

        $started = $this->handleRequest(
            $this->teacherRequest('POST', '/api/v1/tutoring/sessions', $teacher, ['student_id' => $studentId])
        );
        $startedData = $this->assertJsonResponse($started, 201);
        $sessionId = (int) $startedData['data']['id'];

        $response = $this->handleRequest(
            $this->teacherRequest('POST', "/api/v1/tutoring/sessions/{$sessionId}/end", $teacher)
        );
        $data = $this->assertJsonResponse($response, 200);

        $this->assertNotNull($data['data']['ended_at']);

        $row = $this->fetchOne('tutoring_sessions', ['id' => $sessionId]);
        $this->assertNotNull($row['ended_at']);

        $again = $this->handleRequest(
            $this->teacherRequest('POST', "/api/v1/tutoring/sessions/{$sessionId}/end", $teacher)
        );
        $this->assertJsonResponse($again, 404, null, 'NOT_FOUND');
    }

    public function testEndSessionFromAnotherTeacherReturns404(): void
    {
        $owner = $this->seedTeacher();
        $this->seedClassroom($owner['instructor_id']);
        $studentId = $this->seedStudent(['field' => self::FIELD, 'name' => 'TUT_TST_Owner']);

        $started = $this->handleRequest(
            $this->teacherRequest('POST', '/api/v1/tutoring/sessions', $owner, ['student_id' => $studentId])
        );
        $startedData = $this->assertJsonResponse($started, 201);
        $sessionId = (int) $startedData['data']['id'];

        $intruder = $this->seedTeacher();
        $this->seedClassroom($intruder['instructor_id']);

        $response = $this->handleRequest(
            $this->teacherRequest('POST', "/api/v1/tutoring/sessions/{$sessionId}/end", $intruder)
        );
        $this->assertJsonResponse($response, 404, null, 'NOT_FOUND');
    }

    public function testBoardListsClassroomsWithCurrentStudent(): void
    {
        $teacher = $this->seedTeacher();
        $classroomId = $this->seedClassroom($teacher['instructor_id']);
        $studentId = $this->seedStudent([
            'field' => self::FIELD,
            'name' => 'TUT_TST_Board',
            'grade' => 11,
        ]);

        $this->handleRequest(
            $this->teacherRequest('PUT', '/api/v1/tutoring/status', $teacher, ['status' => 'ready'])
        );
        $this->handleRequest(
            $this->teacherRequest('POST', '/api/v1/tutoring/sessions', $teacher, ['student_id' => $studentId])
        );

        $response = $this->handleRequest(
            $this->supporterRequest('GET', '/api/v1/tutoring/board')
        );
        $data = $this->assertJsonResponse($response, 200);

        $this->assertIsArray($data['data']);
        $entry = $this->findBoardEntry($data['data'], $classroomId);
        $this->assertNotNull($entry, 'Board should include the seeded classroom');
        $this->assertSame('TUT_TST_Board', $entry['student']['name']);
        $this->assertSame($studentId, $entry['student']['id']);
        $this->assertSame('ready', $entry['status']);
        $this->assertNotNull($entry['status_since']);
        $this->assertNotNull($entry['entered_at']);
        $this->assertArrayHasKey('teacher_name', $entry);
        $this->assertArrayHasKey('instructor_id', $entry);
    }

    public function testBoardSinceExcludesIdleClassrooms(): void
    {
        $teacher = $this->seedTeacher();
        $classroomId = $this->seedClassroom($teacher['instructor_id']);
        $studentId = $this->seedStudent(['field' => self::FIELD, 'name' => 'TUT_TST_Since']);

        $this->handleRequest(
            $this->teacherRequest('POST', '/api/v1/tutoring/sessions', $teacher, ['student_id' => $studentId])
        );

        $boardRequest = $this->supporterRequest('GET', '/api/v1/tutoring/board')
            ->withQueryParams(['since' => '2999-01-01 00:00:00']);

        $response = $this->handleRequest($boardRequest);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertNull($this->findBoardEntry($data['data'], $classroomId));
    }

    public function testNonTeacherGetsForbiddenOnTeacherEndpoints(): void
    {
        $me = $this->handleRequest($this->supporterRequest('GET', '/api/v1/tutoring/me'));
        $this->assertJsonResponse($me, 403, null, 'FORBIDDEN');

        $status = $this->handleRequest(
            $this->supporterRequest('PUT', '/api/v1/tutoring/status', ['status' => 'ready'], ['Origin' => 'http://localhost'])
        );
        $this->assertJsonResponse($status, 403, null, 'FORBIDDEN');

        $board = $this->handleRequest(
            $this->adminRequest('POST', '/api/v1/tutoring/sessions', ['student_id' => 1], ['Origin' => 'http://localhost'])
        );
        $this->assertJsonResponse($board, 403, null, 'FORBIDDEN');
    }

    /**
     * @param array<int,array<string,mixed>> $board
     * @return array<string,mixed>|null
     */
    private function findBoardEntry(array $board, int $classroomId): ?array
    {
        foreach ($board as $entry) {
            if ((int) $entry['classroom_id'] === $classroomId) {
                return $entry;
            }
        }

        return null;
    }
}
