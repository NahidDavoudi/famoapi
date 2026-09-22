<?php
$base = 'http://localhost:8080';
$results = ['pass' => 0, 'fail' => 0];
$db = new PDO('mysql:host=localhost;dbname=nadcot_famo;charset=utf8mb4','root','');

function test(string $label, string $method, string $path, ?array $headers = [], $body = null, ?int $expectStatus = null, ?callable $assert = null): void {
    global $base, $results;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 10]);
    $h = ['Content-Type: application/json'];
    foreach ($headers ?? [] as $k => $v) $h[] = "$k: $v";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    $data = json_decode($raw, true);
    $status = 'PASS'; $reason = '';
    if ($error) { $status = 'FAIL'; $reason = "curl: $error"; }
    elseif ($expectStatus !== null && $httpCode !== $expectStatus) { $status = 'FAIL'; $reason = "expected HTTP $expectStatus, got $httpCode"; }
    elseif ($data === null && $raw) { $status = 'FAIL'; $reason = 'invalid JSON'; }
    elseif ($assert !== null && !$assert($data, $httpCode)) { $status = 'FAIL'; $reason = 'assertion failed'; }
    if ($status === 'PASS') { $results['pass']++; echo "  ✅ $label\n"; }
    else { $results['fail']++; echo "  ❌ $label — $reason\n"; if (strlen($raw??'')<500) echo "     $raw\n"; }
}
function authHeader(string $token): array { return ['Authorization' => "Bearer $token"]; }

// Get admin token via 2FA
echo "\n=== Admin Token (2FA) ===\n";
$ch = curl_init($base . '/api/v1/auth/login');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>'{"username":"admin","password":"1234"}', CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
$loginResp = json_decode(curl_exec($ch), true); curl_close($ch);
$adminUid = $loginResp['data']['user_id'] ?? 0;
$stmt = $db->prepare('SELECT code FROM login_codes WHERE user_id=? AND used=0 ORDER BY id DESC LIMIT 1');
$stmt->execute([$adminUid]); $code = $stmt->fetchColumn();
$adminToken = '';
if ($code) {
  $ch = curl_init($base . '/api/v1/auth/verify-2fa');
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode(['user_id'=>$adminUid,'code'=>$code]), CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
  $v = json_decode(curl_exec($ch), true); curl_close($ch);
  $adminToken = $v['data']['token'] ?? '';
}
echo "  → Admin token: " . substr($adminToken, 0, 20) . "...\n";

// Get student token (no 2FA)
$ch = curl_init($base . '/api/v1/auth/login');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>'{"username":"09101240664","password":"1234"}', CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
$studentLogin = json_decode(curl_exec($ch), true); curl_close($ch);
$studentToken = $studentLogin['data']['token'] ?? '';
echo "  → Student token: " . substr($studentToken, 0, 20) . "...\n";

echo "\n=== 1. Health ===\n";
test('GET /health','GET','/api/v1/health',expectStatus:200,assert:fn($d)=>$d['success']);

echo "\n=== 2. Auth ===\n";
test('Student login (200)','POST','/api/v1/auth/login',body:['username'=>'09101240664','password'=>'1234'],expectStatus:200,assert:fn($d)=>$d['success']&&isset($d['data']['token']));
test('Admin login (202 2FA)','POST','/api/v1/auth/login',body:['username'=>'admin','password'=>'1234'],expectStatus:202,assert:fn($d)=>$d['success']&&$d['data']['requires_2fa']);
test('Invalid login (401)','POST','/api/v1/auth/login',body:['username'=>'x','password'=>'x'],expectStatus:401);
test('Registration requires password (422)','POST','/api/v1/auth/register',body:[
  'phone'=>'09123456789', 'nationalId'=>'0012345678', 'grade'=>10,
  'field'=>'تجربی', 'name'=>'کاربر تست'
],expectStatus:422,assert:fn($d)=>$d['error']['code']==='VALIDATION_ERROR');
test('GET /me (no token)','GET','/api/v1/auth/me',expectStatus:401);
test('GET /me (valid)','GET','/api/v1/auth/me',headers:authHeader($adminToken),expectStatus:200,assert:fn($d)=>$d['success']);
test('POST /logout','POST','/api/v1/auth/logout',headers:authHeader($adminToken),expectStatus:200);

echo "\n=== 3. Public ===\n";
test('GET /public/courses','GET','/api/v1/public/courses',expectStatus:200,assert:fn($d)=>$d['success']&&count($d['data']['courses']??[])>0);
test('GET /public/instructors','GET','/api/v1/public/instructors',expectStatus:200,assert:fn($d)=>$d['success']&&count($d['data']['instructors']??[])>0);
test('GET /public/supporters','GET','/api/v1/public/supporters',expectStatus:200,assert:fn($d)=>$d['success']);
test('GET /public/blog/posts','GET','/api/v1/public/blog/posts',expectStatus:200,assert:fn($d)=>$d['success']&&$d['pagination']!==null);
test('GET /public/blog/{slug}','GET','/api/v1/public/blog/posts/study',expectStatus:200,assert:fn($d)=>$d['success']);
test('GET /public/blog/categories','GET','/api/v1/public/blog/categories',expectStatus:200,assert:fn($d)=>$d['success']);

echo "\n=== 4. Students ===\n";
test('GET /students','GET','/api/v1/students',headers:authHeader($adminToken),expectStatus:200,assert:fn($d)=>$d['success']&&$d['pagination']['total']>0);
test('GET /students/{id}','GET','/api/v1/students/65',headers:authHeader($adminToken),expectStatus:200,assert:fn($d)=>$d['success']&&$d['data']['id']==65);
test('POST /students (forbidden)','POST','/api/v1/students',headers:authHeader($studentToken),body:['name'=>'test'],expectStatus:403,assert:fn($d)=>$d['error']['code']==='FORBIDDEN');
test('POST toggle-status','POST','/api/v1/students/65/toggle-status',headers:authHeader($adminToken),expectStatus:200,assert:fn($d)=>$d['success']);
test('POST toggle-status (restore)','POST','/api/v1/students/65/toggle-status',headers:authHeader($adminToken),expectStatus:200);
test('GET analytics','GET','/api/v1/students/65/analytics/summary',headers:authHeader($adminToken),expectStatus:200,assert:fn($d)=>$d['success']&&isset($d['data']['student']));

echo "\n=== 5. Courses ===\n";
test('GET /courses','GET','/api/v1/courses',headers:authHeader($adminToken),expectStatus:200,assert:fn($d)=>$d['success']&&count($d['data']??[])>0);
test('GET /courses/{id}','GET','/api/v1/courses/1',headers:authHeader($adminToken),expectStatus:200,assert:fn($d)=>$d['success']&&$d['data']['id']==1);

echo "\n=== 6. Instructors ===\n";
test('GET /instructors','GET','/api/v1/instructors',headers:authHeader($adminToken),expectStatus:200,assert:fn($d)=>$d['success']&&count($d['data']??[])>0);
test('GET /instructors/{id}','GET','/api/v1/instructors/1',headers:authHeader($adminToken),expectStatus:200,assert:fn($d)=>$d['success']&&$d['data']['id']==1);

echo "\n=== 7. Supporters ===\n";
test('GET /supporters','GET','/api/v1/supporters',headers:authHeader($adminToken),expectStatus:200,assert:fn($d)=>$d['success']&&count($d['data']??[])>0);

echo "\n=== 8. Blog ===\n";
test('GET /blog/posts (admin)','GET','/api/v1/blog/posts',headers:authHeader($adminToken),expectStatus:200,assert:fn($d)=>$d['success']);
test('GET /blog/posts/{id}','GET','/api/v1/blog/posts/1',headers:authHeader($adminToken),expectStatus:200,assert:fn($d)=>$d['success']);

echo "\n=== 9. Exams ===\n";
test('GET /exams/dates','GET','/api/v1/exams/dates',headers:authHeader($adminToken),expectStatus:200,assert:fn($d)=>$d['success']&&count($d['data']['dates']??[])>0);
test('GET /exams','GET','/api/v1/exams',headers:authHeader($adminToken),expectStatus:200,assert:fn($d)=>$d['success']);

echo "\n=== 10. Plans ===\n";
test('GET /plans','GET','/api/v1/plans?student_id=65',headers:authHeader($adminToken),expectStatus:200);
test('GET /plans/templates','GET','/api/v1/plans/templates',headers:authHeader($adminToken),expectStatus:200);

echo "\n=== 11. Reports ===\n";
test('GET /reports','GET','/api/v1/reports',headers:authHeader($adminToken),expectStatus:200);
test('GET /reports/stats','GET','/api/v1/reports/stats',headers:authHeader($adminToken),expectStatus:200,assert:fn($d)=>$d['success']&&count($d['data']['stats']??[])>0);

echo "\n=== 12. Files ===\n";
test('GET /files','GET','/api/v1/files',headers:authHeader($adminToken),expectStatus:200,assert:fn($d)=>$d['success']);

echo "\n=== 13. Topics ===\n";
test('GET /topics/0','GET','/api/v1/topics/0',headers:authHeader($adminToken),expectStatus:200);
test('GET /topics/search','GET','/api/v1/topics/search?q=test',headers:authHeader($adminToken),expectStatus:200);

echo "\n=== 14. Appointments ===\n";
test('GET /appointments','GET','/api/v1/appointments',headers:authHeader($adminToken),expectStatus:200,assert:fn($d)=>$d['success']);

echo "\n=== 15. Remedial ===\n";
test('GET /remedial/sessions','GET','/api/v1/remedial/sessions',headers:authHeader($adminToken),expectStatus:200);

echo "\n=== 16. Errors ===\n";
test('404','GET','/api/v1/nonexistent',expectStatus:404,assert:fn($d)=>$d['error']['code']==='NOT_FOUND');

echo "\n═══════════════════════════════════════\n";
$total = $results['pass']+$results['fail'];
echo "  {$results['pass']} PASS / {$results['fail']} FAIL / $total Total\n";
echo ($results['fail']===0?"  ✅ ALL PASS":"  ❌ HAS FAILURES")."\n";
exit($results['fail']===0?0:1);
