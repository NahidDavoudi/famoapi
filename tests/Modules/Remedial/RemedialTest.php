<?php

namespace Tests\Modules\Remedial;

use Tests\TestCase;

class RemedialTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->db->exec("DELETE FROM remedial_sessions");
        $this->db->exec("DELETE FROM remedial_classes");
        $this->db->exec("DELETE FROM remedial_attendance");
        $this->db->exec("DELETE FROM session_students");
        $this->db->exec("DELETE FROM session_student_times");
        $this->db->exec("DELETE FROM students WHERE phone LIKE '0999999999%'");
        $this->db->exec("DELETE FROM users WHERE username LIKE '0999999999%'");
    }

    protected function supporterJsonRequest(string $method, string $uri, array $data = [], array $headers = []): \Psr\Http\Message\ServerRequestInterface
    {
        $token = \App\Core\Auth::encode([
            'id' => 2,
            'role' => 'supporter',
        ]);
        
        $headers = array_merge($headers, [
            'Origin' => 'http://localhost',
        ]);
        
        $request = $this->jsonRequest($method, $uri, $data, $headers);
        return $request->withCookieParams(['famo_jwt' => $token]);
    }

    protected function seedSession(): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO remedial_sessions (title, start_date, end_date, created_at, updated_at)
             VALUES (:title, :start_date, :end_date, NOW(), NOW())"
        );
        $stmt->execute([
            'title' => 'Test Session ' . uniqid(),
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        return (int) $this->db->lastInsertId();
    }

    protected function seedStudent(): int
    {
        $studentId = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM students')->fetchColumn();
        $userId = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM users')->fetchColumn();
        
        $stmt = $this->db->prepare(
            'INSERT INTO students (id, name, grade, field, phone, national_id, is_active, created_at)
             VALUES (:id, :name, :grade, :field, :phone, :national_id, 1, NOW())'
        );
        $stmt->execute([
            'id' => $studentId,
            'name' => 'Test Student ' . uniqid(),
            'grade' => 10,
            'field' => 'ریاضی',
            'phone' => '09999999991',
            'national_id' => rand(1000000000, 9999999999),
        ]);
        
        $stmt = $this->db->prepare(
            'INSERT INTO users (id, username, password_hash, role, linked_id, created_at)
             VALUES (:id, :username, :password_hash, :role, :linked_id, NOW())'
        );
        $stmt->execute([
            'id' => $userId,
            'username' => '09999999991',
            'password_hash' => password_hash('1234', PASSWORD_DEFAULT),
            'role' => 'student',
            'linked_id' => $studentId,
        ]);
        
        return $studentId;
    }

    protected function seedSessionStudent(int $sessionId, int $studentId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO session_students (session_id, student_id) VALUES (?, ?)"
        );
        $stmt->execute([$sessionId, $studentId]);
    }

    public function testUpdateStudentTimeValidField(): void
    {
        $sessionId = $this->seedSession();
        $studentId = $this->seedStudent();
        $this->seedSessionStudent($sessionId, $studentId);
        
        $request = $this->supporterJsonRequest('PUT', '/api/v1/remedial/students/time', [
            'session_id' => $sessionId,
            'student_id' => $studentId,
            'field' => 'start_time',
            'value' => '08:00:00',
        ]);
        
        $response = $this->handleRequest($request);
        $data = $this->assertJsonResponse($response, 200);
        
        $this->assertTrue($data['success']);
    }

    public function testUpdateStudentTimeInvalidFieldReturns422(): void
    {
        $sessionId = $this->seedSession();
        $studentId = $this->seedStudent();
        $this->seedSessionStudent($sessionId, $studentId);
        
        $request = $this->supporterJsonRequest('PUT', '/api/v1/remedial/students/time', [
            'session_id' => $sessionId,
            'student_id' => $studentId,
            'field' => 'invalid_field',
            'value' => '08:00:00',
        ]);
        
        $response = $this->handleRequest($request);
        $this->assertJsonResponse($response, 422, null, 'VALIDATION_ERROR');
    }

    public function testUpdateStudentTimeSqlInjectionAttemptReturns422(): void
    {
        $sessionId = $this->seedSession();
        $studentId = $this->seedStudent();
        $this->seedSessionStudent($sessionId, $studentId);
        
        // Attempt SQL injection via field parameter
        $maliciousField = "start_time) VALUES (1,1,(SELECT SLEEP(3)))--";
        
        $request = $this->supporterJsonRequest('PUT', '/api/v1/remedial/students/time', [
            'session_id' => $sessionId,
            'student_id' => $studentId,
            'field' => $maliciousField,
            'value' => '08:00:00',
        ]);
        
        $response = $this->handleRequest($request);
        
        // Should return 422 validation error, not execute SQL injection
        $this->assertJsonResponse($response, 422, null, 'VALIDATION_ERROR');
    }

    public function testUpdateStudentTimeSqlInjectionInInsertBranchReturns422(): void
    {
        $sessionId = $this->seedSession();
        $studentId = $this->seedStudent();
        // Do NOT add to session_students - this will trigger the INSERT branch
        
        // Attempt SQL injection via field parameter
        $maliciousField = "start_time) VALUES (1,1,(SELECT SLEEP(3)))--";
        
        $request = $this->supporterJsonRequest('PUT', '/api/v1/remedial/students/time', [
            'session_id' => $sessionId,
            'student_id' => $studentId,
            'field' => $maliciousField,
            'value' => '08:00:00',
        ]);
        
        $response = $this->handleRequest($request);
        
        // Should return 422 validation error, not execute SQL injection
        $this->assertJsonResponse($response, 422, null, 'VALIDATION_ERROR');
    }
}