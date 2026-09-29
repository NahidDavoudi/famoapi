<?php

namespace Tests;

use App\Core\Auth;
use App\Core\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected \Slim\App $app;
    protected \PDO $db;
    protected ServerRequestFactory $requestFactory;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->app = $GLOBALS['TEST_APP'];
        $this->db = Database::getConnection();
        $this->requestFactory = new ServerRequestFactory();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    protected function createRequest(
        string $method,
        string $uri,
        array $body = [],
        array $headers = [],
        array $cookies = [],
        array $queryParams = []
    ): ServerRequestInterface {
        $request = $this->requestFactory->createServerRequest($method, $uri);
        
        if (!empty($body)) {
            $request = $request->withParsedBody($body);
        }
        
        if (!empty($headers)) {
            foreach ($headers as $key => $value) {
                $request = $request->withHeader($key, $value);
            }
        }
        
        if (!empty($cookies)) {
            $request = $request->withCookieParams($cookies);
        }
        
        if (!empty($queryParams)) {
            $request = $request->withQueryParams($queryParams);
        }
        
        return $request;
    }

    protected function jsonRequest(
        string $method,
        string $uri,
        array $data = [],
        array $headers = [],
        array $cookies = []
    ): ServerRequestInterface {
        $defaultHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        
        return $this->createRequest($method, $uri, $data, array_merge($defaultHeaders, $headers), $cookies);
    }

    protected function authenticatedRequest(
        string $method,
        string $uri,
        array $userData,
        array $body = [],
        array $headers = [],
        bool $useCookie = true
    ): ServerRequestInterface {
        $token = Auth::encode($userData);
        
        if ($useCookie) {
            return $this->jsonRequest($method, $uri, $body, $headers, ['famo_jwt' => $token]);
        } else {
            $authHeaders = array_merge($headers, ['Authorization' => 'Bearer ' . $token]);
            return $this->jsonRequest($method, $uri, $body, $authHeaders);
        }
    }

    protected function adminRequest(
        string $method,
        string $uri,
        array $body = [],
        array $headers = []
    ): ServerRequestInterface {
        return $this->authenticatedRequest($method, $uri, [
            'id' => 1,
            'role' => 'admin',
        ], $body, $headers);
    }

    protected function supporterRequest(
        string $method,
        string $uri,
        array $body = [],
        array $headers = []
    ): ServerRequestInterface {
        return $this->authenticatedRequest($method, $uri, [
            'id' => 2,
            'role' => 'supporter',
        ], $body, $headers);
    }

    protected function studentRequest(
        string $method,
        string $uri,
        int $studentId,
        array $body = [],
        array $headers = []
    ): ServerRequestInterface {
        return $this->authenticatedRequest($method, $uri, [
            'id' => $studentId,
            'role' => 'student',
            'student_id' => $studentId,
        ], $body, $headers);
    }

    protected function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        return $this->app->handle($request);
    }

    protected function assertJsonResponse(
        ResponseInterface $response,
        int $expectedStatus,
        ?array $expectedDataKeys = null,
        ?string $expectedErrorCode = null
    ): array {
        $this->assertEquals($expectedStatus, $response->getStatusCode());
        
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        
        $this->assertIsArray($data, 'Response body should be valid JSON');
        $this->assertArrayHasKey('success', $data);
        
        if ($expectedStatus >= 400) {
            $this->assertFalse($data['success'], 'Expected failure response');
            $this->assertArrayHasKey('error', $data);
            $this->assertNotNull($data['error']);
            
            if ($expectedErrorCode !== null) {
                $this->assertEquals($expectedErrorCode, $data['error']['code']);
            }
        } else {
            $this->assertTrue($data['success'], 'Expected success response: ' . ($data['error']['message'] ?? ''));
            $this->assertNull($data['error']);
            
            if ($expectedDataKeys !== null) {
                foreach ($expectedDataKeys as $key) {
                    $this->assertArrayHasKey($key, $data['data'], "Expected data key '$key' not found");
                }
            }
        }
        
        return $data;
    }

    protected function assertSuccessResponse(ResponseInterface $response, array $expectedDataKeys = []): array
    {
        return $this->assertJsonResponse($response, 200, $expectedDataKeys);
    }

    protected function assertCreatedResponse(ResponseInterface $response, array $expectedDataKeys = []): array
    {
        return $this->assertJsonResponse($response, 201, $expectedDataKeys);
    }

    protected function assertValidationError(ResponseInterface $response, ?string $expectedField = null): array
    {
        $data = $this->assertJsonResponse($response, 422, null, 'VALIDATION_ERROR');
        
        if ($expectedField !== null) {
            $this->assertStringContainsString($expectedField, $data['error']['message']);
        }
        
        return $data;
    }

    protected function assertNotFound(ResponseInterface $response, ?string $expectedCode = 'NOT_FOUND'): array
    {
        return $this->assertJsonResponse($response, 404, null, $expectedCode);
    }

    protected function assertUnauthorized(ResponseInterface $response): array
    {
        return $this->assertJsonResponse($response, 401, null, 'AUTH_ERROR');
    }

    protected function assertForbidden(ResponseInterface $response): array
    {
        return $this->assertJsonResponse($response, 403, null, 'CSRF_ORIGIN_REJECTED');
    }

    protected function fetchOne(string $table, array $where): ?array
    {
        $conditions = [];
        $params = [];
        foreach ($where as $key => $value) {
            $conditions[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }
        
        $stmt = $this->db->prepare(
            "SELECT * FROM {$table} WHERE " . implode(' AND ', $conditions) . " LIMIT 1"
        );
        $stmt->execute($params);
        
        return $stmt->fetch() ?: null;
    }

    protected function seedStudent(array $data = []): int
    {
        $defaults = [
            'name' => 'Test Student ' . uniqid(),
            'grade' => 10,
            'field' => 'ریاضی',
            'phone' => '09' . rand(100000000, 999999999),
            'national_id' => rand(1000000000, 9999999999),
        ];
        $data = array_merge($defaults, $data);
        
        $userId = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM users')->fetchColumn();
        $studentId = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM students')->fetchColumn();
        
        $stmt = $this->db->prepare(
            'INSERT INTO students (id, name, grade, field, phone, national_id, is_active, created_at)
             VALUES (:id, :name, :grade, :field, :phone, :national_id, 1, NOW())'
        );
        $stmt->execute([
            'id' => $studentId,
            'name' => $data['name'],
            'grade' => $data['grade'],
            'field' => $data['field'],
            'phone' => $data['phone'],
            'national_id' => $data['national_id'],
        ]);
        
        $stmt = $this->db->prepare(
            'INSERT INTO users (id, username, password_hash, role, linked_id, created_at)
             VALUES (:id, :username, :password_hash, :role, :linked_id, NOW())'
        );
        $stmt->execute([
            'id' => $userId,
            'username' => $data['phone'],
            'password_hash' => password_hash('1234', PASSWORD_DEFAULT),
            'role' => 'student',
            'linked_id' => $studentId,
        ]);
        
        return $studentId;
    }
}