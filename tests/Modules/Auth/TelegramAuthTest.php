<?php
declare(strict_types=1);

namespace Tests\Modules\Auth;

use App\Core\Auth;
use App\Modules\Auth\TelegramLoginVerifier;
use App\Modules\Auth\TelegramTicket;
use App\Modules\Bot\TelegramLink;
use Tests\TestCase;

class TelegramAuthTest extends TestCase
{
    private string $secretHex;

    /** @var array<string,string|null> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'TELEGRAM_LOGIN_SECRET_KEY',
            'TELEGRAM_LOGIN_MAX_AGE',
            'TELEGRAM_AUTH_TICKET_TTL',
            'TELEGRAM_BOT_URL',
            'AUTH_2FA_MAX_ATTEMPTS',
            'AUTH_2FA_CHALLENGE_TTL',
            'AUTH_TELEGRAM_VERIFY_PER_IP',
            'AUTH_TELEGRAM_VERIFY_PER_TG',
            'AUTH_TELEGRAM_REGISTER_PER_IP',
            'AUTH_TELEGRAM_REGISTER_PER_TG',
            'AUTH_SMS_PHONE_HOURLY_MAX',
            'AUTH_SMS_IP_HOURLY_MAX',
        ] as $key) {
            $this->envBackup[$key] = $_ENV[$key] ?? null;
        }
        $this->secretHex = hash('sha256', 'test-bot-token');
        $_ENV['TELEGRAM_LOGIN_SECRET_KEY'] = $this->secretHex;
        $_ENV['TELEGRAM_LOGIN_MAX_AGE'] = '300';
        $_ENV['TELEGRAM_AUTH_TICKET_TTL'] = '600';
        unset(
            $_ENV['TELEGRAM_BOT_URL'],
            $_ENV['AUTH_2FA_MAX_ATTEMPTS'],
            $_ENV['AUTH_2FA_CHALLENGE_TTL'],
            $_ENV['AUTH_TELEGRAM_VERIFY_PER_IP'],
            $_ENV['AUTH_TELEGRAM_VERIFY_PER_TG'],
            $_ENV['AUTH_TELEGRAM_REGISTER_PER_IP'],
            $_ENV['AUTH_TELEGRAM_REGISTER_PER_TG'],
            $_ENV['AUTH_SMS_PHONE_HOURLY_MAX'],
            $_ENV['AUTH_SMS_IP_HOURLY_MAX']
        );
        $this->db->exec("DELETE FROM telegram_auth_nonces");
        $this->db->exec("DELETE FROM telegram_2fa_challenges");
        $this->db->exec("DELETE FROM auth_throttle");
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
        parent::tearDown();
    }

    private function widgetPayload(int $id = 880000011, ?int $authDate = null): array
    {
        $payload = [
            'id' => $id,
            'first_name' => 'Test',
            'username' => 'testuser',
            'auth_date' => $authDate ?? time(),
        ];
        $pairs = $payload;
        ksort($pairs, SORT_STRING);
        $lines = [];
        foreach ($pairs as $k => $v) {
            $lines[] = $k . '=' . $v;
        }
        $payload['hash'] = hash_hmac('sha256', implode("\n", $lines), hex2bin($this->secretHex));
        return $payload;
    }

    private function authJsonRequest(string $method, string $uri, array $data = [], array $headers = []): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->jsonRequest($method, $uri, $data, array_merge(['Origin' => 'http://localhost'], $headers));
    }

    /**
     * @return array{supporterId:int,userId:int}
     */
    private function seedSupporter(string $phone): array
    {
        $this->db->exec("DELETE FROM users WHERE username = '$phone'");
        $this->db->exec("DELETE FROM supporters WHERE phone = '$phone'");

        $supporterId = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM supporters')->fetchColumn();
        $userId = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM users')->fetchColumn();

        $stmt = $this->db->prepare(
            'INSERT INTO supporters (id, name, grade, field, phone, chat_id, is_active)
             VALUES (:id, :name, :grade, :field, :phone, NULL, 1)'
        );
        $stmt->execute([
            'id' => $supporterId,
            'name' => 'Test Supporter',
            'grade' => 10,
            'field' => 'ریاضی',
            'phone' => $phone,
        ]);

        $stmt = $this->db->prepare(
            'INSERT INTO users (id, username, password_hash, role, linked_id, full_name, created_at)
             VALUES (:id, :username, :password_hash, :role, :linked_id, :full_name, NOW())'
        );
        $stmt->execute([
            'id' => $userId,
            'username' => $phone,
            'password_hash' => password_hash('1234', PASSWORD_DEFAULT),
            'role' => 'supporter',
            'linked_id' => $supporterId,
            'full_name' => 'Test Supporter',
        ]);

        return ['supporterId' => $supporterId, 'userId' => $userId];
    }

    private function latestLoginCode(int $userId): ?string
    {
        $stmt = $this->db->query('SELECT code FROM login_codes WHERE user_id = ' . $userId . ' ORDER BY id DESC LIMIT 1');
        $code = $stmt->fetchColumn();

        return $code === false ? null : (string) $code;
    }

    private function ticketFor(int $tgId): string
    {
        $response = $this->handleRequest($this->authJsonRequest(
            'POST',
            '/api/v1/auth/telegram/verify',
            $this->widgetPayload($tgId)
        ));
        $data = $this->assertSuccessResponse($response, ['ticket']);

        return (string) $data['data']['ticket'];
    }

    public function testVerifierAcceptsValidPayload(): void
    {
        $tg = TelegramLoginVerifier::verify($this->widgetPayload());
        $this->assertSame(880000011, $tg['id']);
    }

    public function testVerifierRejectsBadHash(): void
    {
        $payload = $this->widgetPayload();
        $payload['hash'] = str_repeat('a', 64);
        $this->expectException(\App\Core\ApiException::class);
        TelegramLoginVerifier::verify($payload);
    }

    public function testVerifierRejectsStaleAuthDate(): void
    {
        $payload = $this->widgetPayload(880000012, time() - 1000);
        $this->expectException(\App\Core\ApiException::class);
        TelegramLoginVerifier::verify($payload);
    }

    public function testVerifierRejectsPayloadReplay(): void
    {
        $payload = $this->widgetPayload(880000013);
        TelegramLoginVerifier::verify($payload);
        $this->expectException(\App\Core\ApiException::class);
        TelegramLoginVerifier::verify($payload);
    }

    public function testTicketIsSingleUse(): void
    {
        $ticket = TelegramTicket::issue(880000014, 'u');
        TelegramTicket::consume($ticket);
        $this->expectException(\App\Core\ApiException::class);
        TelegramTicket::consume($ticket);
    }

    public function testTicketIsNotAcceptedAsSessionJwt(): void
    {
        $ticket = TelegramTicket::issue(880000015, 'u');
        $this->expectException(\Throwable::class);
        Auth::decode($ticket);
    }

    public function testSessionJwtIsNotAcceptedAsTicket(): void
    {
        $jwt = Auth::encode(['id' => 1, 'role' => 'student', 'student_id' => 1]);
        $this->expectException(\App\Core\ApiException::class);
        TelegramTicket::validate($jwt);
    }

    // ---------------------------------------------------------------------
    // Task 7: verify
    // ---------------------------------------------------------------------

    public function testVerifyReturnsTicketWhenNotLinked(): void
    {
        $payload = $this->widgetPayload(880000021);
        $response = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/verify', $payload));
        $data = $this->assertSuccessResponse($response, ['linked', 'ticket', 'telegram']);
        $this->assertFalse($data['data']['linked']);
        $this->assertArrayNotHasKey('token', $data['data']);
    }

    public function testVerifyReturnsLinkedWithoutToken(): void
    {
        $_ENV['TELEGRAM_BOT_URL'] = 'https://t.me/famo_test_bot';
        $tgId = 880000022;
        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id = $tgId");
        $this->db->exec("DELETE FROM users WHERE username = '09990000022'");
        $this->db->exec("DELETE FROM students WHERE phone = '09990000022'");
        $studentId = $this->seedStudent(['phone' => '09990000022']);
        TelegramLink::create($tgId, $tgId, 'student', $studentId);

        $response = $this->handleRequest($this->authJsonRequest(
            'POST',
            '/api/v1/auth/telegram/verify',
            $this->widgetPayload($tgId)
        ));
        $data = $this->assertSuccessResponse($response, ['linked', 'bot_redirect_url']);
        $this->assertTrue($data['data']['linked']);
        $this->assertArrayNotHasKey('token', $data['data']);
        $this->assertEquals('https://t.me/famo_test_bot?start=linked', $data['data']['bot_redirect_url']);
    }

    // ---------------------------------------------------------------------
    // Task 8: register
    // ---------------------------------------------------------------------

    public function testRegisterCreatesStudentAndLink(): void
    {
        $tgId = 880000031;
        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id = $tgId");
        $this->db->exec("DELETE FROM users WHERE username = '09990000031'");
        $this->db->exec("DELETE FROM students WHERE phone = '09990000031'");
        $this->db->exec("DELETE FROM login_codes");

        $ticket = $this->ticketFor($tgId);

        $response = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/register', [
            'ticket' => $ticket,
            'phone' => '09990000031',
            'password' => 'password123',
            'nationalId' => '1234500031',
            'grade' => 10,
            'field' => 'ریاضی',
            'name' => 'TG Student',
        ]));
        $data = $this->assertCreatedResponse($response, ['token', 'user', 'bot_redirect_url']);

        $this->assertEquals('student', $data['data']['user']['role']);

        $student = $this->fetchOne('students', ['phone' => '09990000031']);
        $link = $this->fetchOne('telegram_links', ['telegram_user_id' => $tgId, 'role' => 'student']);
        $this->assertNotNull($student);
        $this->assertNotNull($link);
        $this->assertEquals($student['id'], $link['account_id']);
    }

    public function testRegisterRejectsReusedTicket(): void
    {
        $tgId = 880000032;
        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id = $tgId");
        $this->db->exec("DELETE FROM users WHERE username = '09990000032'");
        $this->db->exec("DELETE FROM students WHERE phone = '09990000032'");

        $ticket = $this->ticketFor($tgId);

        $payload = [
            'ticket' => $ticket,
            'phone' => '09990000032',
            'password' => 'password123',
            'nationalId' => '1234500032',
            'grade' => 10,
            'field' => 'ریاضی',
            'name' => 'TG Reused',
        ];

        $first = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/register', $payload));
        $this->assertCreatedResponse($first, ['token']);

        $second = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/register', $payload));
        $this->assertJsonResponse($second, 401, null, 'TELEGRAM_TICKET_INVALID');
    }

    public function testRegisterValidationFailure(): void
    {
        $response = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/register', [
            'ticket' => 'not-a-real-ticket',
            'phone' => '09990000033',
            'password' => 'password123',
            'nationalId' => '1234500033',
            'grade' => 5,
            'field' => 'ریاضی',
            'name' => 'TG Invalid Grade',
        ]));
        $this->assertValidationError($response);
        $data = json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('پایه تحصیلی', $data['error']['message']);
    }

    // ---------------------------------------------------------------------
    // Task 9: link
    // ---------------------------------------------------------------------

    public function testLinkStudentSuccess(): void
    {
        $tgId = 880000041;
        $phone = '09990000041';
        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id = $tgId");
        $this->db->exec("DELETE FROM users WHERE username = '$phone'");
        $this->db->exec("DELETE FROM students WHERE phone = '$phone'");
        $studentId = $this->seedStudent(['phone' => $phone]);

        $ticket = $this->ticketFor($tgId);

        $response = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/link', [
            'ticket' => $ticket,
            'username' => $phone,
            'password' => '1234',
        ]));
        $data = $this->assertSuccessResponse($response, ['token', 'user', 'link']);

        $this->assertEquals('student', $data['data']['user']['role']);

        $link = $this->fetchOne('telegram_links', ['telegram_user_id' => $tgId, 'role' => 'student']);
        $this->assertNotNull($link);
        $this->assertEquals($studentId, $link['account_id']);
        $this->assertEquals($tgId, $link['chat_id']);
    }

    public function testLinkBadCredentialsDoesNotCreateLink(): void
    {
        $tgId = 880000042;
        $phone = '09990000042';
        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id = $tgId");
        $this->db->exec("DELETE FROM users WHERE username = '$phone'");
        $this->db->exec("DELETE FROM students WHERE phone = '$phone'");
        $this->seedStudent(['phone' => $phone]);

        $ticket = $this->ticketFor($tgId);

        $response = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/link', [
            'ticket' => $ticket,
            'username' => $phone,
            'password' => 'wrong-password',
        ]));
        $this->assertJsonResponse($response, 401, null, 'AUTH_ERROR');
        $this->assertNull($this->fetchOne('telegram_links', ['telegram_user_id' => $tgId]));
    }

    public function testLinkUnsupportedRole(): void
    {
        $tgId = 880000043;
        $phone = '09990000043';
        $this->db->exec("DELETE FROM users WHERE username = '$phone'");
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, password_hash, role, full_name, created_at)
             VALUES (:username, :password_hash, :role, :full_name, NOW())'
        );
        $stmt->execute([
            'username' => $phone,
            'password_hash' => password_hash('1234', PASSWORD_DEFAULT),
            'role' => 'teacher',
            'full_name' => 'TG Teacher',
        ]);

        $ticket = $this->ticketFor($tgId);

        $response = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/link', [
            'ticket' => $ticket,
            'username' => $phone,
            'password' => '1234',
        ]));
        $this->assertJsonResponse($response, 403, null, 'TELEGRAM_LINK_ROLE_UNSUPPORTED');
    }

    public function testSupporterLinkCreatesBoundChallenge(): void
    {
        $tgId = 880000044;
        $phone = '09990000044';
        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id = $tgId");
        $supporter = $this->seedSupporter($phone);

        $ticket = $this->ticketFor($tgId);

        $response = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/link', [
            'ticket' => $ticket,
            'username' => $phone,
            'password' => '1234',
        ]));
        $data = $this->assertJsonResponse($response, 202, ['requires_2fa', 'challenge_id', 'phone_mask']);

        $this->assertTrue($data['data']['requires_2fa']);
        $this->assertArrayNotHasKey('user_id', $data['data']);
        $this->assertArrayNotHasKey('token', $data['data']);

        $challenge = $this->fetchOne('telegram_2fa_challenges', [
            'challenge_nonce' => $data['data']['challenge_id'],
        ]);
        $this->assertNotNull($challenge);
        $this->assertEquals($supporter['userId'], (int) $challenge['user_id']);

        $claims = TelegramTicket::validate($ticket);
        $this->assertEquals(TelegramTicket::jti($claims), $challenge['ticket_jti']);

        $this->assertNull($this->fetchOne('telegram_links', ['telegram_user_id' => $tgId]));
    }

    public function testLinkConflictNotRevealedBeforeAuthentication(): void
    {
        $tgId = 880000045;
        $otherTg = 880000145;
        $phone = '09990000045';
        $this->db->exec("DELETE FROM users WHERE username = '$phone'");
        $this->db->exec("DELETE FROM students WHERE phone = '$phone'");
        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id IN ($tgId, $otherTg)");
        $studentId = $this->seedStudent(['phone' => $phone]);
        TelegramLink::create($otherTg, $otherTg, 'student', $studentId);

        $ticket = $this->ticketFor($tgId);

        $response = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/link', [
            'ticket' => $ticket,
            'username' => $phone,
            'password' => 'wrong-password',
        ]));
        $this->assertJsonResponse($response, 401, null, 'AUTH_ERROR');
        $this->assertNull($this->fetchOne('telegram_links', ['telegram_user_id' => $tgId]));
    }

    public function testLinkAccountConflictOnlyAfterAuthentication(): void
    {
        $tgId = 880000046;
        $otherTg = 880000146;
        $phone = '09990000046';
        $this->db->exec("DELETE FROM users WHERE username = '$phone'");
        $this->db->exec("DELETE FROM students WHERE phone = '$phone'");
        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id IN ($tgId, $otherTg)");
        $studentId = $this->seedStudent(['phone' => $phone]);
        TelegramLink::create($otherTg, $otherTg, 'student', $studentId);

        $ticket = $this->ticketFor($tgId);

        $response = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/link', [
            'ticket' => $ticket,
            'username' => $phone,
            'password' => '1234',
        ]));
        $this->assertJsonResponse($response, 409, null, 'ACCOUNT_ALREADY_LINKED');
    }

    // ---------------------------------------------------------------------
    // Task 10: verify-2fa
    // ---------------------------------------------------------------------

    public function testVerify2faSuccessLinksSupporter(): void
    {
        $tgId = 880000051;
        $phone = '09990000051';
        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id = $tgId");
        $supporter = $this->seedSupporter($phone);

        $ticket = $this->ticketFor($tgId);

        $linkResponse = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/link', [
            'ticket' => $ticket,
            'username' => $phone,
            'password' => '1234',
        ]));
        $linkData = $this->assertJsonResponse($linkResponse, 202, ['challenge_id']);
        $challengeId = (string) $linkData['data']['challenge_id'];
        $code = $this->latestLoginCode($supporter['userId']);
        $this->assertNotNull($code);

        $response = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/verify-2fa', [
            'ticket' => $ticket,
            'challenge_id' => $challengeId,
            'code' => $code,
        ]));
        $data = $this->assertSuccessResponse($response, ['token', 'user', 'bot_redirect_url']);
        $this->assertEquals('supporter', $data['data']['user']['role']);

        $link = $this->fetchOne('telegram_links', ['telegram_user_id' => $tgId, 'role' => 'supporter']);
        $this->assertNotNull($link);
        $this->assertEquals($supporter['supporterId'], (int) $link['account_id']);

        $challenge = $this->fetchOne('telegram_2fa_challenges', ['challenge_nonce' => $challengeId]);
        $this->assertNotNull($challenge['consumed_at']);
    }

    public function testVerify2faRejectsMismatchedUserId(): void
    {
        $tgId = 880000052;
        $phone = '09990000052';
        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id = $tgId");
        $supporter = $this->seedSupporter($phone);

        $ticket = $this->ticketFor($tgId);
        $linkResponse = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/link', [
            'ticket' => $ticket,
            'username' => $phone,
            'password' => '1234',
        ]));
        $linkData = $this->assertJsonResponse($linkResponse, 202, ['challenge_id']);
        $challengeId = (string) $linkData['data']['challenge_id'];
        $code = $this->latestLoginCode($supporter['userId']);
        $this->assertNotNull($code);

        $response = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/verify-2fa', [
            'ticket' => $ticket,
            'challenge_id' => $challengeId,
            'code' => $code,
            'user_id' => $supporter['userId'] + 9999,
        ]));
        $this->assertJsonResponse($response, 401, null, '2FA_ERROR');
        $this->assertNull($this->fetchOne('telegram_links', ['telegram_user_id' => $tgId]));
    }

    public function testVerify2faAttemptCapReturnsRateLimited(): void
    {
        $_ENV['AUTH_2FA_MAX_ATTEMPTS'] = '1';
        $tgId = 880000053;
        $phone = '09990000053';
        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id = $tgId");
        $this->seedSupporter($phone);

        $ticket = $this->ticketFor($tgId);
        $linkResponse = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/link', [
            'ticket' => $ticket,
            'username' => $phone,
            'password' => '1234',
        ]));
        $linkData = $this->assertJsonResponse($linkResponse, 202, ['challenge_id']);
        $challengeId = (string) $linkData['data']['challenge_id'];

        $response = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/verify-2fa', [
            'ticket' => $ticket,
            'challenge_id' => $challengeId,
            'code' => '000000',
        ]));
        $this->assertJsonResponse($response, 429, null, 'RATE_LIMITED');
        $this->assertNotSame('', $response->getHeaderLine('Retry-After'));
    }

    public function testVerify2faRejectsExpiredChallenge(): void
    {
        $tgId = 880000054;
        $phone = '09990000054';
        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id = $tgId");
        $supporter = $this->seedSupporter($phone);

        $ticket = $this->ticketFor($tgId);
        $linkResponse = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/link', [
            'ticket' => $ticket,
            'username' => $phone,
            'password' => '1234',
        ]));
        $linkData = $this->assertJsonResponse($linkResponse, 202, ['challenge_id']);
        $challengeId = (string) $linkData['data']['challenge_id'];
        $code = $this->latestLoginCode($supporter['userId']);
        $this->assertNotNull($code);

        $this->db->exec(
            "UPDATE telegram_2fa_challenges SET expires_at = UTC_TIMESTAMP() - INTERVAL 60 SECOND
             WHERE challenge_nonce = " . $this->db->quote($challengeId)
        );

        $response = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/verify-2fa', [
            'ticket' => $ticket,
            'challenge_id' => $challengeId,
            'code' => $code,
        ]));
        $this->assertJsonResponse($response, 401, null, '2FA_ERROR');
    }

    public function testVerify2faRejectsReusedChallenge(): void
    {
        $tgId = 880000055;
        $phone = '09990000055';
        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id = $tgId");
        $supporter = $this->seedSupporter($phone);

        $ticket = $this->ticketFor($tgId);
        $linkResponse = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/link', [
            'ticket' => $ticket,
            'username' => $phone,
            'password' => '1234',
        ]));
        $linkData = $this->assertJsonResponse($linkResponse, 202, ['challenge_id']);
        $challengeId = (string) $linkData['data']['challenge_id'];
        $code = $this->latestLoginCode($supporter['userId']);
        $this->assertNotNull($code);

        $first = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/verify-2fa', [
            'ticket' => $ticket,
            'challenge_id' => $challengeId,
            'code' => $code,
        ]));
        $this->assertSuccessResponse($first, ['token']);

        $second = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/verify-2fa', [
            'ticket' => $ticket,
            'challenge_id' => $challengeId,
            'code' => $code,
        ]));
        $this->assertJsonResponse($second, 401, null, '2FA_ERROR');
    }

    public function testVerify2faAccountConflictOnlyAfterSuccessful2fa(): void
    {
        $tgId = 880000056;
        $otherTg = 880000156;
        $phone = '09990000056';
        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id IN ($tgId, $otherTg)");
        $supporter = $this->seedSupporter($phone);
        TelegramLink::create($otherTg, $otherTg, 'supporter', $supporter['supporterId']);

        $ticket = $this->ticketFor($tgId);
        $linkResponse = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/link', [
            'ticket' => $ticket,
            'username' => $phone,
            'password' => '1234',
        ]));
        $linkData = $this->assertJsonResponse($linkResponse, 202, ['challenge_id']);
        $challengeId = (string) $linkData['data']['challenge_id'];
        $code = $this->latestLoginCode($supporter['userId']);
        $this->assertNotNull($code);

        $response = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/verify-2fa', [
            'ticket' => $ticket,
            'challenge_id' => $challengeId,
            'code' => $code,
        ]));
        $this->assertJsonResponse($response, 409, null, 'ACCOUNT_ALREADY_LINKED');
    }
}
