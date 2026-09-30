<?php

namespace Tests\Modules\Auth;

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\SmsService;
use App\Modules\Auth\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Tests\TestCase;

class TeacherAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->db->exec("DELETE FROM login_codes WHERE user_id IN (SELECT id FROM users WHERE username LIKE 'teacher_test_%')");
        $this->db->exec("DELETE FROM users WHERE username LIKE 'teacher_test_%'");
        $this->db->exec("DELETE FROM instructors WHERE name LIKE 'TeacherTest%'");
    }

    private function seedTeacher(): array
    {
        $username = 'teacher_test_' . uniqid();
        $password = 'password123';

        $instructorId = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM instructors')->fetchColumn();
        $stmt = $this->db->prepare(
            'INSERT INTO instructors (id, name, title, display_order)
             VALUES (:id, :name, :title, 0)'
        );
        $stmt->execute([
            'id' => $instructorId,
            'name' => 'TeacherTest ' . $username,
            'title' => 'مدرس',
        ]);

        $userId = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM users')->fetchColumn();
        $stmt = $this->db->prepare(
            'INSERT INTO users (id, username, password_hash, role, linked_id, created_at)
             VALUES (:id, :username, :password_hash, :role, :linked_id, NOW())'
        );
        $stmt->execute([
            'id' => $userId,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'teacher',
            'linked_id' => $instructorId,
        ]);

        return [
            'user_id' => $userId,
            'instructor_id' => $instructorId,
            'username' => $username,
            'password' => $password,
        ];
    }

    public function testTeacherLoginRequires2fa(): void
    {
        $teacher = $this->seedTeacher();

        $service = new AuthService($this->fakeSms());
        $result = $service->login($teacher['username'], $teacher['password']);

        $this->assertTrue($result['requires_2fa'] ?? false, 'Teacher login must require 2FA');
        $this->assertEquals('teacher', $result['role']);
        $this->assertEquals($teacher['user_id'], $result['user_id']);

        $code = $this->fetchOne('login_codes', ['user_id' => $teacher['user_id']]);
        $this->assertNotNull($code, 'A login code should be issued for the teacher');
    }

    public function testVerify2faTeacherTokenCarriesInstructorId(): void
    {
        $teacher = $this->seedTeacher();

        $code = '654321';
        $stmt = $this->db->prepare('INSERT INTO login_codes (user_id, code, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([$teacher['user_id'], $code, gmdate('Y-m-d H:i:s', time() + 300)]);

        $request = $this->jsonRequest('POST', '/api/v1/auth/verify-2fa', [
            'user_id' => $teacher['user_id'],
            'code' => $code,
        ], ['Origin' => 'http://localhost']);

        $response = $this->handleRequest($request);
        $data = $this->assertSuccessResponse($response, ['token', 'user']);

        $decoded = Auth::decode($data['data']['token']);
        $this->assertEquals('teacher', $decoded->role);
        $this->assertEquals($teacher['instructor_id'], $decoded->instructor_id);
        $this->assertEquals($teacher['instructor_id'], $data['data']['user']['instructor_id']);
    }

    public function testAuthEncodeIncludesInstructorId(): void
    {
        $teacherToken = Auth::encode(['id' => 10, 'role' => 'teacher', 'instructor_id' => 7]);
        $teacherPayload = Auth::decode($teacherToken);
        $this->assertEquals(7, $teacherPayload->instructor_id);
        $this->assertEquals('teacher', $teacherPayload->role);

        $studentToken = Auth::encode(['id' => 11, 'role' => 'student', 'student_id' => 3]);
        $studentPayload = Auth::decode($studentToken);
        $this->assertNull($studentPayload->instructor_id);
        $this->assertEquals(3, $studentPayload->student_id);
    }

    public function testTeacherAuthorizationAllowsOnlyTeacher(): void
    {
        $middleware = new Authorization('teacher');

        $allowed = $middleware->process(
            $this->createRequest('GET', '/guard')->withAttribute('user', (object) ['role' => 'teacher']),
            $this->passThroughHandler()
        );
        $this->assertEquals(200, $allowed->getStatusCode());

        $denied = $middleware->process(
            $this->createRequest('GET', '/guard')->withAttribute('user', (object) ['role' => 'admin']),
            $this->passThroughHandler()
        );
        $this->assertEquals(403, $denied->getStatusCode());
        $payload = json_decode((string) $denied->getBody(), true);
        $this->assertEquals('FORBIDDEN', $payload['error']['code']);
        $this->assertEquals('دسترسی محدود به مدرسان', $payload['error']['message']);
    }

    private function fakeSms(): SmsService
    {
        return new class extends SmsService {
            public function sendVerificationCode(string $mobile, string $code): bool
            {
                return true;
            }
        };
    }

    private function passThroughHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };
    }
}
