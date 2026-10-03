<?php
declare(strict_types=1);

namespace Tests\Modules\Auth;

use App\Modules\Bot\BotActor;
use Tests\TestCase;

class TelegramAdminLinkTest extends TestCase
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
            'AUTH_SMS_PHONE_HOURLY_MAX',
            'AUTH_SMS_IP_HOURLY_MAX',
        ] as $key) {
            $this->envBackup[$key] = $_ENV[$key] ?? null;
        }
        $this->secretHex = hash('sha256', 'test-bot-token');
        $_ENV['TELEGRAM_LOGIN_SECRET_KEY'] = $this->secretHex;
        $_ENV['TELEGRAM_LOGIN_MAX_AGE'] = '300';
        $_ENV['TELEGRAM_AUTH_TICKET_TTL'] = '600';
        unset($_ENV['TELEGRAM_BOT_URL'], $_ENV['AUTH_2FA_MAX_ATTEMPTS'], $_ENV['AUTH_2FA_CHALLENGE_TTL']);
        unset($_ENV['AUTH_SMS_PHONE_HOURLY_MAX'], $_ENV['AUTH_SMS_IP_HOURLY_MAX']);

        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id BETWEEN 886000000 AND 886000999");
        $this->db->exec("DELETE FROM login_codes WHERE user_id BETWEEN 9860000 AND 9860099");
        $this->db->exec("DELETE FROM telegram_auth_nonces");
        $this->db->exec("DELETE FROM telegram_2fa_challenges");
        $this->db->exec("DELETE FROM auth_throttle");
        $this->db->exec("DELETE FROM users WHERE username LIKE '09986000%'");
        $this->db->exec("DELETE FROM admins WHERE id BETWEEN 9860000 AND 9860099");
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

    private function widgetPayload(int $id): array
    {
        $payload = [
            'id' => $id,
            'first_name' => 'Admin',
            'username' => 'adminuser',
            'auth_date' => time(),
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

    private function seedAdmin(int $adminId, int $userId, string $phone): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO admins (id, name, chat_id, phone, created_at)
             VALUES (:id, :name, 0, :phone, NOW())'
        );
        $stmt->execute(['id' => $adminId, 'name' => 'Test Admin', 'phone' => $phone]);

        $stmt = $this->db->prepare(
            'INSERT INTO users (id, username, password_hash, role, linked_id, full_name, created_at)
             VALUES (:id, :username, :password_hash, :role, :linked_id, :full_name, NOW())'
        );
        $stmt->execute([
            'id'            => $userId,
            'username'      => $phone,
            'password_hash' => password_hash('1234', PASSWORD_DEFAULT),
            'role'          => 'admin',
            'linked_id'     => $adminId,
            'full_name'     => 'Test Admin',
        ]);
    }

    private function latestLoginCode(int $userId): ?string
    {
        $code = $this->db->query(
            'SELECT code FROM login_codes WHERE user_id = ' . $userId . ' ORDER BY id DESC LIMIT 1'
        )->fetchColumn();

        return $code === false ? null : (string) $code;
    }

    public function testAdminLinkRequiresTwoFactorAndStoresAdminRole(): void
    {
        $adminId = 9860001;
        $userId = 9860001;
        $phone = '09986000001';
        $tgId = 886000001;
        $this->seedAdmin($adminId, $userId, $phone);

        $ticket = $this->ticketFor($tgId);

        $linkResponse = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/link', [
            'ticket'   => $ticket,
            'username' => $phone,
            'password' => '1234',
        ]));
        $linkData = $this->assertJsonResponse($linkResponse, 202, ['requires_2fa', 'challenge_id', 'phone_mask']);
        $challengeId = (string) $linkData['data']['challenge_id'];
        $this->assertNull($this->fetchOne('telegram_links', ['telegram_user_id' => $tgId, 'role' => 'admin']));

        $code = $this->latestLoginCode($userId);
        $this->assertNotNull($code);

        $verify = $this->handleRequest($this->authJsonRequest('POST', '/api/v1/auth/telegram/verify-2fa', [
            'ticket'       => $ticket,
            'challenge_id' => $challengeId,
            'code'         => $code,
        ]));
        $verifyData = $this->assertSuccessResponse($verify, ['token', 'user', 'bot_redirect_url']);
        $this->assertSame('admin', $verifyData['data']['user']['role']);

        $link = $this->fetchOne('telegram_links', ['telegram_user_id' => $tgId, 'role' => 'admin']);
        $this->assertNotNull($link);
        $this->assertSame($adminId, (int) $link['account_id']);

        $actor = BotActor::resolve('admin', $tgId, $tgId);
        $this->assertSame('admin', $actor['role']);
        $this->assertSame($adminId, $actor['account_id']);
        $this->assertSame('Test Admin', $actor['name']);
    }

    public function testBotActorRejectsInactiveAdminLink(): void
    {
        $adminId = 9860002;
        $tgId = 886000002;
        $this->seedAdmin($adminId, 9860002, '09986000002');

        // No admins row for this account -> not resolvable.
        $this->db->exec("DELETE FROM admins WHERE id = $adminId");
        \App\Modules\Bot\TelegramLink::create($tgId, $tgId, 'admin', $adminId);

        $this->expectException(\App\Core\ApiException::class);
        BotActor::resolve('admin', $tgId, $tgId);
    }
}
