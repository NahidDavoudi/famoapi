<?php

namespace Tests\Modules\Auth;

use Tests\TestCase;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->db->exec("DELETE FROM login_codes");
        $this->db->exec("DELETE FROM users WHERE username LIKE 'test_%'");
        $this->db->exec("DELETE FROM students WHERE phone LIKE '0999999999%'");
    }

    protected function authJsonRequest(string $method, string $uri, array $data = [], array $headers = []): \Psr\Http\Message\ServerRequestInterface
    {
        $headers = array_merge($headers, [
            'Origin' => 'http://localhost',
        ]);
        return $this->jsonRequest($method, $uri, $data, $headers);
    }

    public function testLoginSuccess(): void
    {
        $phone = '09999999991';
        $password = 'password123';
        
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, password_hash, role, full_name, created_at)
             VALUES (:username, :password_hash, :role, :full_name, NOW())'
        );
        $stmt->execute([
            'username' => $phone,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'student',
            'full_name' => 'Test Student',
        ]);
        $userId = (int) $this->db->lastInsertId();
        
        $request = $this->authJsonRequest('POST', '/api/v1/auth/login', [
            'username' => $phone,
            'password' => $password,
        ]);
        
        $response = $this->handleRequest($request);
        $data = $this->assertSuccessResponse($response, ['token', 'user']);
        
        $this->assertNotEmpty($data['data']['token']);
        $this->assertEquals('student', $data['data']['user']['role']);
    }

    public function testLoginInvalidCredentials(): void
    {
        $request = $this->authJsonRequest('POST', '/api/v1/auth/login', [
            'username' => '09999999999',
            'password' => 'wrongpassword',
        ]);
        
        $response = $this->handleRequest($request);
        $this->assertJsonResponse($response, 401, null, 'AUTH_ERROR');
    }

    public function testLoginMissingFields(): void
    {
        $request = $this->authJsonRequest('POST', '/api/v1/auth/login', []);
        
        $response = $this->handleRequest($request);
        $this->assertValidationError($response);
        $data = json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('نام کاربری', $data['error']['message']);
    }

    public function testRegisterSuccess(): void
    {
        $phone = '09999999992';
        $nationalId = '1234567890';
        
        $this->db->exec("DELETE FROM users WHERE username = '$phone'");
        $this->db->exec("DELETE FROM students WHERE phone = '$phone'");
        
        $request = $this->authJsonRequest('POST', '/api/v1/auth/register', [
            'phone' => $phone,
            'password' => 'password123',
            'nationalId' => $nationalId,
            'grade' => 10,
            'field' => 'ریاضی',
            'name' => 'Test Register Student',
        ]);
        
        $response = $this->handleRequest($request);
        $data = $this->assertCreatedResponse($response, ['token', 'user']);
        
        $this->assertNotEmpty($data['data']['token']);
        $this->assertEquals('student', $data['data']['user']['role']);
        
        $student = $this->fetchOne('students', ['phone' => $phone]);
        $this->assertNotNull($student);
        $this->assertEquals($nationalId, $student['national_id']);
    }

    public function testRegisterDuplicatePhone(): void
    {
        $phone = '09999999993';
        
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, password_hash, role, created_at)
             VALUES (:username, :password_hash, :role, NOW())'
        );
        $stmt->execute([
            'username' => $phone,
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'student',
        ]);
        
        $request = $this->authJsonRequest('POST', '/api/v1/auth/register', [
            'phone' => $phone,
            'password' => 'password123',
            'nationalId' => '1234567891',
            'grade' => 10,
            'field' => 'ریاضی',
            'name' => 'Test Duplicate',
        ]);
        
        $response = $this->handleRequest($request);
        $this->assertJsonResponse($response, 409, null, 'REGISTRATION_ERROR');
    }

    public function testRegisterInvalidPhone(): void
    {
        $request = $this->authJsonRequest('POST', '/api/v1/auth/register', [
            'phone' => 'invalid-phone',
            'password' => 'password123',
            'nationalId' => '1234567892',
            'grade' => 10,
            'field' => 'ریاضی',
            'name' => 'Test Invalid Phone',
        ]);
        
        $response = $this->handleRequest($request);
        $this->assertValidationError($response);
        $data = json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('شماره تلفن', $data['error']['message']);
    }

    public function testRegisterInvalidNationalId(): void
    {
        $request = $this->authJsonRequest('POST', '/api/v1/auth/register', [
            'phone' => '09999999994',
            'password' => 'password123',
            'nationalId' => 'invalid',
            'grade' => 10,
            'field' => 'ریاضی',
            'name' => 'Test Invalid National ID',
        ]);
        
        $response = $this->handleRequest($request);
        $this->assertValidationError($response);
        $data = json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('کد ملی', $data['error']['message']);
    }

    public function testRegisterInvalidGrade(): void
    {
        $request = $this->authJsonRequest('POST', '/api/v1/auth/register', [
            'phone' => '09999999995',
            'password' => 'password123',
            'nationalId' => '1234567893',
            'grade' => 5,
            'field' => 'ریاضی',
            'name' => 'Test Invalid Grade',
        ]);
        
        $response = $this->handleRequest($request);
        $this->assertValidationError($response);
        $data = json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('پایه تحصیلی', $data['error']['message']);
    }

    public function testVerify2faSuccess(): void
    {
        $phone = '09999999996';
        $password = 'password123';
        
        $this->db->exec("DELETE FROM users WHERE username = '$phone'");
        $this->db->exec("DELETE FROM login_codes WHERE user_id IN (SELECT id FROM users WHERE username = '$phone')");
        
        $userId = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM users')->fetchColumn();
        
        $stmt = $this->db->prepare(
            'INSERT INTO users (id, username, password_hash, role, full_name, created_at)
             VALUES (:id, :username, :password_hash, :role, :full_name, NOW())'
        );
        $stmt->execute([
            'id' => $userId,
            'username' => $phone,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'admin',
            'full_name' => 'Test Admin',
        ]);
        
        $code = '123456';
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 300);
        
        $stmt = $this->db->prepare(
            'INSERT INTO login_codes (user_id, code, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $code, $expiresAt]);
        
        $request = $this->authJsonRequest('POST', '/api/v1/auth/verify-2fa', [
            'user_id' => $userId,
            'code' => $code,
        ]);
        
        $response = $this->handleRequest($request);
        $data = $this->assertSuccessResponse($response, ['token', 'user']);
        
        $this->assertNotEmpty($data['data']['token']);
        $this->assertEquals('admin', $data['data']['user']['role']);
        
        $usedCode = $this->fetchOne('login_codes', ['user_id' => $userId, 'code' => $code]);
        $this->assertEquals(1, $usedCode['used']);
    }

    public function testVerify2faInvalidCode(): void
    {
        $phone = '09999999997';
        $password = 'password123';
        
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, password_hash, role, full_name, created_at)
             VALUES (:username, :password_hash, :role, :full_name, NOW())'
        );
        $stmt->execute([
            'username' => $phone,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'admin',
            'full_name' => 'Test Admin 2',
        ]);
        $userId = (int) $this->db->lastInsertId();
        
        $request = $this->authJsonRequest('POST', '/api/v1/auth/verify-2fa', [
            'user_id' => $userId,
            'code' => '999999',
        ]);
        
        $response = $this->handleRequest($request);
        $this->assertJsonResponse($response, 401, null, '2FA_ERROR');
    }

    public function testMeWithValidToken(): void
    {
        $phone = '09999999998';
        $password = 'password123';
        
        $this->db->exec("DELETE FROM users WHERE username = '$phone'");
        
        $userId = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM users')->fetchColumn();
        
        $stmt = $this->db->prepare(
            'INSERT INTO users (id, username, password_hash, role, full_name, created_at)
             VALUES (:id, :username, :password_hash, :role, :full_name, NOW())'
        );
        $stmt->execute([
            'id' => $userId,
            'username' => $phone,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'student',
            'full_name' => 'Test Student Me',
        ]);
        
        $request = $this->authenticatedRequest('GET', '/api/v1/auth/me', [
            'id' => $userId,
            'role' => 'student',
        ]);
        
        $response = $this->handleRequest($request);
        $data = $this->assertSuccessResponse($response, ['id', 'username', 'role']);
        
        $this->assertEquals($userId, $data['data']['id']);
        $this->assertEquals($phone, $data['data']['username']);
        $this->assertEquals('student', $data['data']['role']);
    }

    public function testMeWithoutToken(): void
    {
        $request = $this->createRequest('GET', '/api/v1/auth/me', [], ['Origin' => 'http://localhost']);
        
        $response = $this->handleRequest($request);
        $this->assertJsonResponse($response, 401, null, 'UNAUTHORIZED');
    }

    public function testMeWithInvalidToken(): void
    {
        $request = $this->jsonRequest('GET', '/api/v1/auth/me', [], ['Origin' => 'http://localhost'], ['famo_jwt' => 'invalid.token.here']);
        
        $response = $this->handleRequest($request);
        $this->assertJsonResponse($response, 401, null, 'UNAUTHORIZED');
    }

    public function testLogout(): void
    {
        $request = $this->authJsonRequest('POST', '/api/v1/auth/logout');
        
        $response = $this->handleRequest($request);
        $data = $this->assertSuccessResponse($response);
        
        $setCookie = $response->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('famo_jwt=', $setCookie);
        $this->assertStringContainsString('Max-Age=0', $setCookie);
    }

    public function testLoginWithCookieMode(): void
    {
        $phone = '09999999999';
        $password = 'password123';
        
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, password_hash, role, full_name, created_at)
             VALUES (:username, :password_hash, :role, :full_name, NOW())'
        );
        $stmt->execute([
            'username' => $phone,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'student',
            'full_name' => 'Test Cookie Student',
        ]);
        
        $request = $this->authJsonRequest('POST', '/api/v1/auth/login', [
            'username' => $phone,
            'password' => $password,
        ], ['X-Auth-Mode' => 'cookie']);
        
        $response = $this->handleRequest($request);
        $data = $this->assertSuccessResponse($response, ['user']);
        
        $this->assertArrayNotHasKey('token', $data['data']);
        
        $setCookie = $response->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('famo_jwt=', $setCookie);
        $this->assertStringContainsString('HttpOnly', $setCookie);
    }
}