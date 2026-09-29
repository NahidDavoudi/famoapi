<?php

namespace Tests\Modules\Students;

use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class StudentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->db->exec("DELETE FROM students WHERE phone LIKE '0999999999%'");
        $this->db->exec("DELETE FROM users WHERE username LIKE '0999999999%'");
    }

protected function adminJsonRequest(string $method, string $uri, array $data = [], array $headers = []): \Psr\Http\Message\ServerRequestInterface
    {
        $token = \App\Core\Auth::encode([
            'id' => 1,
            'role' => 'admin',
        ]);
        
        $headers = array_merge($headers, [
            'Origin' => 'http://localhost',
        ]);
        
        $request = $this->jsonRequest($method, $uri, $data, $headers);
        return $request->withCookieParams(['famo_jwt' => $token]);
    }

    protected function assertListResponse(ResponseInterface $response, int $expectedCount = 0): array
    {
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertIsArray($data['data']);
        
        if ($expectedCount > 0) {
            $this->assertGreaterThanOrEqual($expectedCount, count($data['data']));
        }
        
        return $data;
    }

    protected function assertItemResponse(ResponseInterface $response, array $expectedKeys = []): array
    {
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $data['data'], "Expected key '$key' not found in data");
        }
        
        return $data;
    }

    public function testListStudents(): void
    {
        $studentId = $this->seedStudent([
            'phone' => '09999999991',
            'name' => 'Test Student 1',
        ]);
        
        $request = $this->authenticatedRequest('GET', '/api/v1/students', [
            'id' => 1,
            'role' => 'admin',
        ]);
        
        $response = $this->handleRequest($request);
        $data = $this->assertListResponse($response, 1);
        
        $this->assertGreaterThanOrEqual(1, count($data['data']));
    }

    public function testListStudentsWithPagination(): void
    {
        $request = $this->authenticatedRequest('GET', '/api/v1/students?page=1&perPage=5', [
            'id' => 1,
            'role' => 'admin',
        ]);
        
        $response = $this->handleRequest($request);
        $data = $this->assertListResponse($response);
        
        $this->assertArrayHasKey('page', $data['pagination']);
        $this->assertArrayHasKey('per_page', $data['pagination']);
        $this->assertArrayHasKey('total', $data['pagination']);
    }

    public function testListStudentsWithSearch(): void
    {
        $this->seedStudent([
            'phone' => '09999999992',
            'name' => 'Searchable Student',
        ]);
        
        $request = $this->authenticatedRequest('GET', '/api/v1/students?search=Searchable', [
            'id' => 1,
            'role' => 'admin',
        ]);
        
        $response = $this->handleRequest($request);
        $data = $this->assertListResponse($response, 1);
        
        $this->assertEquals(1, count($data['data']));
        $this->assertEquals('Searchable Student', $data['data'][0]['name']);
    }

    public function testListStudentsWithFilters(): void
    {
        $this->seedStudent([
            'phone' => '09999999993',
            'name' => 'Grade 10 Student',
            'grade' => 10,
            'field' => 'ریاضی',
        ]);
        
        $request = $this->authenticatedRequest('GET', '/api/v1/students?grade=10&field=ریاضی', [
            'id' => 1,
            'role' => 'admin',
        ]);
        
        $response = $this->handleRequest($request);
        $data = $this->assertListResponse($response);
        
        foreach ($data['data'] as $student) {
            $this->assertEquals(10, (int)$student['grade']);
            $this->assertEquals('ریاضی', $student['field']);
        }
    }

    public function testCreateStudent(): void
    {
        $request = $this->adminJsonRequest('POST', '/api/v1/students', [
            'name' => 'New Student',
            'phone' => '09999999994',
            'grade' => 11,
            'field' => 'تجربی',
            'nationalId' => '1234567894',
        ], ['Origin' => 'http://localhost']);
        
        $response = $this->handleRequest($request);
        $data = $this->assertJsonResponse($response, 201);
        
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals('New Student', $data['data']['name']);
        $this->assertEquals('09999999994', $data['data']['phone']);
        $this->assertEquals(11, (int)$data['data']['grade']);
        $this->assertEquals('تجربی', $data['data']['field']);
        
        $student = $this->fetchOne('students', ['phone' => '09999999994']);
        $this->assertNotNull($student);
        $this->assertEquals('1234567894', $student['national_id']);
    }

    public function testCreateStudentValidationErrors(): void
    {
        $request = $this->adminJsonRequest('POST', '/api/v1/students', [
            'name' => '',
            'phone' => 'invalid',
            'grade' => 5,
            'field' => 'invalid',
            'nationalId' => 'invalid',
        ], ['Origin' => 'http://localhost']);
        
        $response = $this->handleRequest($request);
        $this->assertJsonResponse($response, 422, null, 'VALIDATION_ERROR');
    }

    public function testGetStudent(): void
    {
        $studentId = $this->seedStudent([
            'phone' => '09999999995',
            'name' => 'Get Student',
        ]);
        
        $request = $this->authenticatedRequest('GET', "/api/v1/students/{$studentId}", [
            'id' => 1,
            'role' => 'admin',
        ]);
        
        $response = $this->handleRequest($request);
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals($studentId, (int)$data['data']['id']);
        $this->assertEquals('Get Student', $data['data']['name']);
    }

    public function testGetStudentNotFound(): void
    {
        $request = $this->authenticatedRequest('GET', '/api/v1/students/99999', [
            'id' => 1,
            'role' => 'admin',
        ]);
        
        $response = $this->handleRequest($request);
        $this->assertJsonResponse($response, 404, null, 'NOT_FOUND');
    }

    public function testUpdateStudent(): void
    {
        $studentId = $this->seedStudent([
            'phone' => '09999999996',
            'name' => 'Original Name',
        ]);
        
        $request = $this->adminJsonRequest('PUT', "/api/v1/students/{$studentId}", [
            'name' => 'Updated Name',
            'phone' => '09999999997',
        ], ['Origin' => 'http://localhost']);
        
        $response = $this->handleRequest($request);
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertTrue($data['success']);
        $this->assertEquals('Updated Name', $data['data']['name']);
        $this->assertEquals('09999999997', $data['data']['phone']);
        
        $student = $this->fetchOne('students', ['id' => $studentId]);
        $this->assertEquals('Updated Name', $student['name']);
        $this->assertEquals('09999999997', $student['phone']);
    }

    public function testUpdateStudentNotFound(): void
    {
        $request = $this->adminJsonRequest('PUT', '/api/v1/students/99999', [
            'name' => 'Updated Name',
        ], ['Origin' => 'http://localhost']);
        
        $response = $this->handleRequest($request);
        $this->assertJsonResponse($response, 404, null, 'UPDATE_ERROR');
    }

    public function testDeleteStudent(): void
    {
        $studentId = $this->seedStudent([
            'phone' => '09999999998',
            'name' => 'To Delete',
        ]);
        
        $request = $this->adminJsonRequest('DELETE', "/api/v1/students/{$studentId}", [], ['Origin' => 'http://localhost']);
        
        $response = $this->handleRequest($request);
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertTrue($data['success']);
        
        $student = $this->fetchOne('students', ['id' => $studentId]);
        $this->assertNull($student);
    }

    public function testDeleteStudentNotFound(): void
    {
        $request = $this->adminJsonRequest('DELETE', '/api/v1/students/99999', [], ['Origin' => 'http://localhost']);
        
        $response = $this->handleRequest($request);
        $this->assertJsonResponse($response, 404, null, 'DELETE_ERROR');
    }

    public function testGetList(): void
    {
        $this->seedStudent(['phone' => '09999999999', 'name' => 'List Student']);
        
        $request = $this->authenticatedRequest('GET', '/api/v1/students/list', [
            'id' => 1,
            'role' => 'admin',
        ]);
        
        $response = $this->handleRequest($request);
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        $this->assertGreaterThanOrEqual(1, count($data['data']));
    }

    public function testOverview(): void
    {
        $request = $this->authenticatedRequest('GET', '/api/v1/students/overview', [
            'id' => 1,
            'role' => 'admin',
        ]);
        
        $response = $this->handleRequest($request);
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('students', $data['data']);
        $this->assertIsArray($data['data']['students']);
    }

    public function testCreateAccount(): void
    {
        $studentId = $this->seedStudent([
            'phone' => '09999999990',
            'name' => 'Account Student',
        ]);
        
        $this->db->exec("DELETE FROM users WHERE linked_id = {$studentId} AND role = 'student'");
        
        $request = $this->adminJsonRequest('POST', "/api/v1/students/{$studentId}/create-account");
        
        $response = $this->handleRequest($request);
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        error_log("DEBUG CreateAccount: decoded data keys=" . json_encode(array_keys($decoded['data'] ?? [])));
        
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertTrue($data['success']);
        $this->assertEquals($studentId, (int)$data['data']['id']);
        $this->assertArrayHasKey('user_id', $data['data']);
        $this->assertNotNull($data['data']['user_id']);
        
        $student = $this->fetchOne('students', ['id' => $studentId]);
        $this->assertNotNull($student['user_id']);
    }

    public function testCreateAccountAlreadyExists(): void
    {
        $studentId = $this->seedStudent([
            'phone' => '09999999989',
            'name' => 'Has Account',
        ]);
        
        $request = $this->adminJsonRequest('POST', "/api/v1/students/{$studentId}/create-account", [], ['Origin' => 'http://localhost']);
        
        $response = $this->handleRequest($request);
        $this->assertJsonResponse($response, 409, null, 'ACCOUNT_ERROR');
    }

    public function testResetPassword(): void
    {
        $studentId = $this->seedStudent([
            'phone' => '09999999988',
            'name' => 'Reset Password',
        ]);
        
        $request = $this->adminJsonRequest('POST', "/api/v1/students/{$studentId}/reset-password", [], ['Origin' => 'http://localhost']);
        
        $response = $this->handleRequest($request);
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertTrue($data['success']);
        $this->assertEquals($studentId, (int)$data['data']['id']);
    }

    public function testResetPasswordNoAccount(): void
    {
        $studentId = $this->seedStudent([
            'phone' => '09999999987',
            'name' => 'No Account',
        ]);
        
        $this->db->exec("DELETE FROM users WHERE linked_id = {$studentId} AND role = 'student'");
        
        $request = $this->adminJsonRequest('POST', "/api/v1/students/{$studentId}/reset-password", [], ['Origin' => 'http://localhost']);
        
        $response = $this->handleRequest($request);
        $this->assertJsonResponse($response, 400, null, 'RESET_ERROR');
    }

    public function testToggleStatus(): void
    {
        $studentId = $this->seedStudent([
            'phone' => '09999999986',
            'name' => 'Toggle Student',
        ]);
        
        $studentBefore = $this->fetchOne('students', ['id' => $studentId]);
        $this->assertEquals(1, (int)$studentBefore['is_active']);
        
        $request = $this->adminJsonRequest('POST', "/api/v1/students/{$studentId}/toggle-status", [], ['Origin' => 'http://localhost']);
        
        $response = $this->handleRequest($request);
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertTrue($data['success']);
        $this->assertEquals(0, (int)$data['data']['is_active']);
        
        $studentAfter = $this->fetchOne('students', ['id' => $studentId]);
        $this->assertEquals(0, (int)$studentAfter['is_active']);
    }

    public function testAnalyticsSummary(): void
    {
        $studentId = $this->seedStudent([
            'phone' => '09999999985',
            'name' => 'Analytics Student',
        ]);
        
        $request = $this->authenticatedRequest('GET', "/api/v1/students/{$studentId}/analytics/summary", [
            'id' => 1,
            'role' => 'admin',
        ]);
        
        $response = $this->handleRequest($request);
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('student', $data['data']);
        $this->assertArrayHasKey('plan_count', $data['data']);
        $this->assertArrayHasKey('exam_count', $data['data']);
    }

    public function testAnalyticsWeekDetail(): void
    {
        $studentId = $this->seedStudent([
            'phone' => '09999999984',
            'name' => 'Week Detail Student',
        ]);
        
        $request = $this->authenticatedRequest('GET', "/api/v1/students/{$studentId}/analytics/week-detail", [
            'id' => 1,
            'role' => 'admin',
        ]);
        
        $response = $this->handleRequest($request);
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('student', $data['data']);
        $this->assertArrayHasKey('schedule', $data['data']);
    }

    public function testAnalyticsSubjectStats(): void
    {
        $studentId = $this->seedStudent([
            'phone' => '09999999983',
            'name' => 'Subject Stats Student',
        ]);
        
        $request = $this->authenticatedRequest('GET', "/api/v1/students/{$studentId}/analytics/subject-stats", [
            'id' => 1,
            'role' => 'admin',
        ]);
        
        $response = $this->handleRequest($request);
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('student', $data['data']);
        $this->assertArrayHasKey('subjects', $data['data']);
    }

    public function testAnalyticsExamTrend(): void
    {
        $studentId = $this->seedStudent([
            'phone' => '09999999982',
            'name' => 'Exam Trend Student',
        ]);
        
        $request = $this->authenticatedRequest('GET', "/api/v1/students/{$studentId}/analytics/exam-trend", [
            'id' => 1,
            'role' => 'admin',
        ]);
        
        $response = $this->handleRequest($request);
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('student', $data['data']);
        $this->assertArrayHasKey('trend', $data['data']);
    }
}