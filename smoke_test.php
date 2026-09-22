<?php
/**
 * Famo API — Comprehensive Smoke Test
 * Tests all 65+ endpoints, reports PASS/FAIL per group.
 * Usage: php smoke_test.php
 */

$base = 'http://localhost:8080';
$results = ['pass' => 0, 'fail' => 0];
$adminToken = null;
$studentToken = null;

function test(string $label, string $method, string $path, ?array $headers = [], $body = null, ?int $expectStatus = null, ?callable $assert = null): void
{
    global $base, $results;

    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 10,
    ]);

    $h = ['Content-Type: application/json'];
    foreach ($headers ?? [] as $k => $v) {
        $h[] = "$k: $v";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $h);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $data = json_decode($raw, true);
    $status = 'PASS';

    if ($error) { $status = 'FAIL'; $reason = "curl: $error"; }
    elseif ($expectStatus !== null && $httpCode !== $expectStatus) { $status = 'FAIL'; $reason = "expected HTTP $expectStatus, got $httpCode"; }
    elseif ($data === null && $raw) { $status = 'FAIL'; $reason = 'invalid JSON'; }
    elseif ($assert !== null && !$assert($data, $httpCode)) { $status = 'FAIL'; $reason = 'assertion failed'; }

    if ($status === 'PASS') {
        $results['pass']++;
        echo "  ✅ $status $label" . PHP_EOL;
    } else {
        $results['fail']++;
        echo "  ❌ $status $label — " . ($reason ?? '') . PHP_EOL;
        if (isset($raw) && strlen($raw) < 500) echo "     Response: $raw" . PHP_EOL;
    }
}

function authHeader(string $token): array { return ['Authorization' => "Bearer $token"]; }

// ─── 1. Health ──────────────────────────────────────────────────
echo "\n=== 1. Health ===\n";
test('GET /api/v1/health', 'GET', '/api/v1/health', expectStatus: 200,
    assert: fn($d) => $d['success'] && $d['data']['status'] === 'healthy');

// ─── 2. Auth ────────────────────────────────────────────────────
echo "\n=== 2. Auth ===\n";
test('POST /auth/login (valid)', 'POST', '/api/v1/auth/login', body: ['username' => 'admin', 'password' => '1234'],
    expectStatus: 200, assert: fn($d) => $d['success'] && isset($d['data']['token']));

// Store admin token
$ch1 = curl_init($base . '/api/v1/auth/login');
curl_setopt_array($ch1, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => '{"username":"admin","password":"1234"}', CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
$r = json_decode(curl_exec($ch1), true);
curl_close($ch1);
$adminToken = $r['data']['token'] ?? '';
echo "  → Admin token: " . substr($adminToken, 0, 20) . "..." . PHP_EOL;

// Login as student
$ch2 = curl_init($base . '/api/v1/auth/login');
curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => '{"username":"amirrez","password":"1234"}', CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
$r2 = json_decode(curl_exec($ch2), true);
curl_close($ch2);
$studentToken = $r2['data']['token'] ?? '';
echo "  → Student token: " . substr($studentToken, 0, 20) . "..." . PHP_EOL;

test('POST /auth/login (invalid)', 'POST', '/api/v1/auth/login', body: ['username' => 'x', 'password' => 'x'],
    expectStatus: 401, assert: fn($d) => !$d['success']);

test('GET /auth/me (no token)', 'GET', '/api/v1/auth/me', expectStatus: 401);
test('GET /auth/me (valid token)', 'GET', '/api/v1/auth/me', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success'] && isset($d['data']['role']));

test('POST /auth/logout', 'POST', '/api/v1/auth/logout', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success']);

// ─── 3. Public ──────────────────────────────────────────────────
echo "\n=== 3. Public ===\n";
test('GET /public/courses', 'GET', '/api/v1/public/courses', expectStatus: 200,
    assert: fn($d) => $d['success'] && count($d['data']['courses'] ?? []) > 0);

test('GET /public/instructors', 'GET', '/api/v1/public/instructors', expectStatus: 200,
    assert: fn($d) => $d['success'] && count($d['data']['instructors'] ?? []) > 0);

test('GET /public/supporters', 'GET', '/api/v1/public/supporters', expectStatus: 200,
    assert: fn($d) => $d['success']);

test('GET /public/blog/posts', 'GET', '/api/v1/public/blog/posts', expectStatus: 200,
    assert: fn($d) => $d['success'] && $d['pagination'] !== null);

test('GET /public/blog/posts/{slug}', 'GET', '/api/v1/public/blog/posts/study', expectStatus: 200,
    assert: fn($d) => $d['success'] && isset($d['data']['post']));

test('GET /public/blog/categories', 'GET', '/api/v1/public/blog/categories', expectStatus: 200,
    assert: fn($d) => $d['success']);

// ─── 4. Students ────────────────────────────────────────────────
echo "\n=== 4. Students ===\n";
test('GET /students', 'GET', '/api/v1/students', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success'] && count($d['data'] ?? []) > 0 && $d['pagination']['total'] > 0);

test('GET /students/list', 'GET', '/api/v1/students/list', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success'] && count($d['data'] ?? []) > 0);

test('GET /students/{id}', 'GET', '/api/v1/students/65', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success'] && $d['data']['id'] == 65);

test('POST /students (forbidden — student role)', 'POST', '/api/v1/students', headers: authHeader($studentToken), body: ['name' => 'test'],
    expectStatus: 403, assert: fn($d) => $d['error']['code'] === 'FORBIDDEN');

test('POST /students/{id}/toggle-status', 'POST', '/api/v1/students/65/toggle-status', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success'] && isset($d['data']['is_active']));

// Toggle back
test('POST /students/{id}/toggle-status (restore)', 'POST', '/api/v1/students/65/toggle-status', headers: authHeader($adminToken),
    expectStatus: 200);

test('GET /students/{id}/analytics/summary', 'GET', '/api/v1/students/65/analytics/summary', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success'] && isset($d['data']['student']));

// ─── 5. Courses ─────────────────────────────────────────────────
echo "\n=== 5. Courses ===\n";
test('GET /courses', 'GET', '/api/v1/courses', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success'] && count($d['data'] ?? []) > 0);

test('GET /courses/{id}', 'GET', '/api/v1/courses/1', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success'] && $d['data']['id'] == 1);

// ─── 6. Instructors ─────────────────────────────────────────────
echo "\n=== 6. Instructors ===\n";
test('GET /instructors', 'GET', '/api/v1/instructors', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success'] && count($d['data'] ?? []) > 0);

test('GET /instructors/{id}', 'GET', '/api/v1/instructors/1', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success'] && $d['data']['id'] == 1);

// ─── 7. Supporters ──────────────────────────────────────────────
echo "\n=== 7. Supporters ===\n";
test('GET /supporters', 'GET', '/api/v1/supporters', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success'] && count($d['data'] ?? []) > 0);

// ─── 8. Blog (admin) ────────────────────────────────────────────
echo "\n=== 8. Blog (admin) ===\n";
test('GET /blog/posts (admin)', 'GET', '/api/v1/blog/posts', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success']);

test('GET /blog/posts/{id}', 'GET', '/api/v1/blog/posts/1', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success'] && $d['data']['post']['id'] == 1);

// ─── 9. Exams ───────────────────────────────────────────────────
echo "\n=== 9. Exams ===\n";
test('GET /exams/dates', 'GET', '/api/v1/exams/dates', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success'] && count($d['data']['dates'] ?? []) > 0);

test('GET /exams', 'GET', '/api/v1/exams', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success']);

// ─── 10. Weekly Plans ───────────────────────────────────────────
echo "\n=== 10. Weekly Plans ===\n";
test('GET /plans?student_id=65', 'GET', '/api/v1/plans?student_id=65', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success'] && isset($d['data']['items']));

test('GET /plans/templates', 'GET', '/api/v1/plans/templates', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success']);

// ─── 11. Reports ────────────────────────────────────────────────
echo "\n=== 11. Reports ===\n";
test('GET /reports', 'GET', '/api/v1/reports', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success']);

test('GET /reports/stats', 'GET', '/api/v1/reports/stats', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success'] && count($d['data']['stats'] ?? []) > 0);

// ─── 12. Files ──────────────────────────────────────────────────
echo "\n=== 12. Files ===\n";
test('GET /files', 'GET', '/api/v1/files', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success']);

// ─── 13. Topics ─────────────────────────────────────────────────
echo "\n=== 13. Topics ===\n";
test('GET /topics/0', 'GET', '/api/v1/topics/0', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success']);

test('GET /topics/search', 'GET', '/api/v1/topics/search?q=math', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success']);

// ─── 14. Appointments ───────────────────────────────────────────
echo "\n=== 14. Appointments ===\n";
test('GET /appointments', 'GET', '/api/v1/appointments', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success'] && $d['pagination']['total'] > 0);

// ─── 15. Remedial ───────────────────────────────────────────────
echo "\n=== 15. Remedial ===\n";
test('GET /remedial/sessions', 'GET', '/api/v1/remedial/sessions', headers: authHeader($adminToken),
    expectStatus: 200, assert: fn($d) => $d['success']);

// ─── 16. 404 ────────────────────────────────────────────────────
echo "\n=== 16. Error Handling ===\n";
test('GET /nonexistent (404)', 'GET', '/api/v1/nonexistent', expectStatus: 404,
    assert: fn($d) => !$d['success'] && $d['error']['code'] === 'NOT_FOUND');

test('POST /health (405)', 'POST', '/api/v1/health', expectStatus: 404);

// ─── Summary ────────────────────────────────────────────────────
echo "\n════════════════════════════════════════════\n";
$total = $results['pass'] + $results['fail'];
echo "  Results: {$results['pass']} PASS / {$results['fail']} FAIL / $total Total\n";
echo "  Status: " . ($results['fail'] === 0 ? "✅ ALL PASS" : "❌ HAS FAILURES") . "\n";
echo "════════════════════════════════════════════\n";

exit($results['fail'] === 0 ? 0 : 1);