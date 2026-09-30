<?php

namespace Tests\Modules\Bot;

use Psr\Http\Message\ServerRequestInterface;
use Tests\TestCase;

class BotGatewayTest extends TestCase
{
    private const KEY = 'test-bot-key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id BETWEEN 880000000 AND 880000999");
        $this->db->exec("DELETE FROM student_supporter_assignments WHERE supporter_id BETWEEN 9700000 AND 9700999");
        $this->db->exec("DELETE FROM supporters WHERE id BETWEEN 9700000 AND 9700999");
        $this->db->exec("DELETE FROM users WHERE username LIKE '09998887%'");
        $this->db->exec("DELETE FROM students WHERE phone LIKE '09998887%'");
    }

    private function bot(string $method, string $uri, array $body = [], array $headers = []): ServerRequestInterface
    {
        return $this->jsonRequest($method, $uri, $body, array_merge(['X-Bot-Key' => self::KEY], $headers));
    }

    private function actorHeaders(int $telegramUserId, string $role): array
    {
        return [
            'X-Bot-Role'          => $role,
            'X-Telegram-User-Id'  => (string) $telegramUserId,
            'X-Telegram-Chat-Id'  => (string) $telegramUserId,
        ];
    }

    public function testPingWithoutKeyIsUnauthorized(): void
    {
        $response = $this->handleRequest($this->jsonRequest('GET', '/api/v1/bot/ping'));

        $this->assertJsonResponse($response, 401, null, 'BOT_UNAUTHORIZED');
    }

    public function testPingWithWrongKeyIsUnauthorized(): void
    {
        $response = $this->handleRequest(
            $this->jsonRequest('GET', '/api/v1/bot/ping', [], ['X-Bot-Key' => 'wrong'])
        );

        $this->assertJsonResponse($response, 401, null, 'BOT_UNAUTHORIZED');
    }

    public function testPingWithKeySucceeds(): void
    {
        $response = $this->handleRequest($this->bot('GET', '/api/v1/bot/ping'));
        $data = $this->assertJsonResponse($response, 200, ['status', 'iran_date', 'iran_date_jalali']);

        $this->assertSame('ok', $data['data']['status']);
    }

    public function testLinkResolveMeUnlinkAndBlockFlow(): void
    {
        $studentId = $this->seedStudent(['phone' => '09998887001', 'name' => 'Bot Student']);
        $telegramUserId = 880000001;

        // Link
        $link = $this->handleRequest($this->bot('POST', '/api/v1/bot/identity/link', [
            'telegram_user_id' => $telegramUserId,
            'chat_id'          => $telegramUserId,
            'role'             => 'student',
            'account_id'       => $studentId,
            'contact_verified' => true,
        ]));
        $linkData = $this->assertJsonResponse($link, 200, ['role', 'account_id', 'linked_at']);
        $this->assertSame('student', $linkData['data']['role']);
        $this->assertEquals($studentId, $linkData['data']['account_id']);

        // Resolve
        $resolve = $this->handleRequest(
            $this->bot('GET', '/api/v1/bot/identity/resolve?telegram_user_id=' . $telegramUserId)
        );
        $resolveData = $this->assertJsonResponse($resolve, 200, ['links']);
        $this->assertCount(1, $resolveData['data']['links']);

        // Acting account
        $me = $this->handleRequest($this->bot('GET', '/api/v1/bot/me', [], $this->actorHeaders($telegramUserId, 'student')));
        $meData = $this->assertJsonResponse($me, 200, ['role', 'account_id', 'is_blocked']);
        $this->assertEquals($studentId, $meData['data']['account_id']);

        // Block then the acting endpoint is refused
        $this->handleRequest($this->bot('POST', '/api/v1/bot/identity/block', [
            'telegram_user_id' => $telegramUserId,
            'role'             => 'student',
        ]));
        $blocked = $this->handleRequest($this->bot('GET', '/api/v1/bot/me', [], $this->actorHeaders($telegramUserId, 'student')));
        $this->assertJsonResponse($blocked, 403, null, 'BOT_ACCOUNT_BLOCKED');

        // Unblock restores access
        $this->handleRequest($this->bot('POST', '/api/v1/bot/identity/unblock', [
            'telegram_user_id' => $telegramUserId,
            'role'             => 'student',
        ]));
        $unblocked = $this->handleRequest($this->bot('GET', '/api/v1/bot/me', [], $this->actorHeaders($telegramUserId, 'student')));
        $this->assertJsonResponse($unblocked, 200);

        // Unlink removes the link
        $unlink = $this->handleRequest($this->bot('POST', '/api/v1/bot/identity/unlink', [
            'telegram_user_id' => $telegramUserId,
            'role'             => 'student',
        ]));
        $unlinkData = $this->assertJsonResponse($unlink, 200, ['unlinked']);
        $this->assertSame(1, $unlinkData['data']['unlinked']);

        $after = $this->handleRequest(
            $this->bot('GET', '/api/v1/bot/identity/resolve?telegram_user_id=' . $telegramUserId)
        );
        $afterData = $this->assertJsonResponse($after, 200, ['links']);
        $this->assertCount(0, $afterData['data']['links']);
    }

    public function testLinkRequiresContactVerification(): void
    {
        $studentId = $this->seedStudent(['phone' => '09998887002', 'name' => 'Unverified']);

        $response = $this->handleRequest($this->bot('POST', '/api/v1/bot/identity/link', [
            'telegram_user_id' => 880000002,
            'chat_id'          => 880000002,
            'role'             => 'student',
            'account_id'       => $studentId,
            'contact_verified' => false,
        ]));

        $this->assertJsonResponse($response, 422, null, 'CONTACT_NOT_VERIFIED');
    }

    public function testLinkRejectsInactiveStudent(): void
    {
        $studentId = $this->seedStudent(['phone' => '09998887003', 'name' => 'Inactive']);
        $this->db->exec("UPDATE students SET is_active = 0 WHERE id = {$studentId}");

        $response = $this->handleRequest($this->bot('POST', '/api/v1/bot/identity/link', [
            'telegram_user_id' => 880000003,
            'chat_id'          => 880000003,
            'role'             => 'student',
            'account_id'       => $studentId,
            'contact_verified' => true,
        ]));

        $this->assertJsonResponse($response, 403, null, 'ACCOUNT_INACTIVE');
    }

    public function testLookupReturnsAllRolesForPhone(): void
    {
        $phone = '09998887004';
        $this->seedStudent(['phone' => $phone, 'name' => 'Lookup Student']);

        $stmt = $this->db->prepare(
            'INSERT INTO supporters (id, name, grade, field, phone, is_active) VALUES (?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([9700004, 'Lookup Supporter', 12, 'ریاضی', $phone]);

        $response = $this->handleRequest($this->bot('POST', '/api/v1/bot/identity/lookup', ['phone' => $phone]));
        $data = $this->assertJsonResponse($response, 200, ['phone', 'identities']);

        $this->assertSame($phone, $data['data']['phone']);
        $roles = array_column($data['data']['identities'], 'role');
        $this->assertContains('student', $roles);
        $this->assertContains('supporter', $roles);
    }
}
