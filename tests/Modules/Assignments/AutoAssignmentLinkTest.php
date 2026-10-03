<?php
declare(strict_types=1);

namespace Tests\Modules\Assignments;

use App\Modules\Supporters\Supporter;
use Tests\TestCase;

class AutoAssignmentLinkTest extends TestCase
{
    private const BOT_KEY = 'test-bot-key';

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
        ] as $key) {
            $this->envBackup[$key] = $_ENV[$key] ?? null;
        }
        $this->secretHex = hash('sha256', 'test-bot-token');
        $_ENV['TELEGRAM_LOGIN_SECRET_KEY'] = $this->secretHex;
        $_ENV['TELEGRAM_LOGIN_MAX_AGE'] = '300';
        $_ENV['TELEGRAM_AUTH_TICKET_TTL'] = '600';
        unset($_ENV['TELEGRAM_BOT_URL']);

        $this->db->exec("DELETE FROM student_supporter_assignments WHERE supporter_id BETWEEN 9730000 AND 9730099");
        $this->db->exec("DELETE FROM student_supporter_assignments WHERE student_id IN (SELECT id FROM students WHERE phone LIKE '09973300%')");
        $this->db->exec("DELETE FROM supporter_scopes WHERE supporter_id BETWEEN 9730000 AND 9730099");
        $this->db->exec("DELETE FROM supporters WHERE id BETWEEN 9730000 AND 9730099");
        $this->db->exec("DELETE FROM telegram_links WHERE telegram_user_id BETWEEN 883000000 AND 883000999");
        $this->db->exec("DELETE FROM users WHERE username LIKE '09973300%'");
        $this->db->exec("DELETE FROM students WHERE phone LIKE '09973300%'");
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

    private function seedSupporter(int $id, string $name, string $field, int $grade, array $scopes): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO supporters (id, name, grade, field, phone, chat_id, is_active)
             VALUES (?, ?, ?, ?, NULL, NULL, 1)'
        );
        $stmt->execute([$id, $name, $grade, $field]);
        Supporter::replaceScopes($id, $scopes);
    }

    private function botLink(array $body): \Psr\Http\Message\ResponseInterface
    {
        return $this->handleRequest($this->jsonRequest(
            'POST',
            '/api/v1/bot/identity/link',
            $body,
            ['X-Bot-Key' => self::BOT_KEY]
        ));
    }

    private function widgetPayload(int $id): array
    {
        $payload = [
            'id' => $id,
            'first_name' => 'Test',
            'username' => 'testuser',
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

    private function ticketFor(int $tgId): string
    {
        $response = $this->handleRequest($this->jsonRequest(
            'POST',
            '/api/v1/auth/telegram/verify',
            $this->widgetPayload($tgId),
            ['Origin' => 'http://localhost']
        ));
        $data = $this->assertSuccessResponse($response, ['ticket']);

        return (string) $data['data']['ticket'];
    }

    public function testBotSupporterLinkAutoAssignsMatchingStudents(): void
    {
        $supporterId = 9730001;
        $this->seedSupporter($supporterId, 'Auto Link Supporter', 'زبان تست', 7, [
            ['field' => 'زبان تست', 'grade' => 7],
        ]);

        $first = $this->seedStudent(['phone' => '09973300071', 'name' => 'Auto Link 1', 'field' => 'زبان تست', 'grade' => 7]);
        $second = $this->seedStudent(['phone' => '09973300072', 'name' => 'Auto Link 2', 'field' => 'زبان تست', 'grade' => 7]);

        $response = $this->botLink([
            'telegram_user_id' => 883000001,
            'chat_id'          => 883000001,
            'role'             => 'supporter',
            'account_id'       => $supporterId,
            'contact_verified' => true,
        ]);
        $this->assertJsonResponse($response, 200, ['role', 'account_id']);

        foreach ([$first, $second] as $studentId) {
            $row = $this->fetchOne('student_supporter_assignments', [
                'student_id' => $studentId,
                'is_active'  => 1,
            ]);
            $this->assertNotNull($row);
            $this->assertSame($supporterId, (int) $row['supporter_id']);
        }
    }

    public function testBotStudentLinkAutoAssignsWhenExactlyOneSupporterMatches(): void
    {
        $supporterId = 9730002;
        $this->seedSupporter($supporterId, 'Student Direction', 'هنر تست', 7, [
            ['field' => 'هنر تست', 'grade' => 7],
        ]);

        $studentId = $this->seedStudent(['phone' => '09973300081', 'name' => 'Student Direction', 'field' => 'هنر تست', 'grade' => 7]);

        $response = $this->botLink([
            'telegram_user_id' => 883000002,
            'chat_id'          => 883000002,
            'role'             => 'student',
            'account_id'       => $studentId,
            'contact_verified' => true,
        ]);
        $this->assertJsonResponse($response, 200, ['role', 'account_id']);

        $row = $this->fetchOne('student_supporter_assignments', [
            'student_id' => $studentId,
            'is_active'  => 1,
        ]);
        $this->assertNotNull($row);
        $this->assertSame($supporterId, (int) $row['supporter_id']);
    }

    public function testBotStudentLinkLeavesAmbiguousMatchUnassigned(): void
    {
        $this->seedSupporter(9730003, 'Ambiguous A', 'عمومی تست', 7, [['field' => 'عمومی تست', 'grade' => 7]]);
        $this->seedSupporter(9730004, 'Ambiguous B', 'عمومی تست', 7, [['field' => 'عمومی تست', 'grade' => 7]]);

        $studentId = $this->seedStudent(['phone' => '09973300091', 'name' => 'Ambiguous Link', 'field' => 'عمومی تست', 'grade' => 7]);

        $response = $this->botLink([
            'telegram_user_id' => 883000003,
            'chat_id'          => 883000003,
            'role'             => 'student',
            'account_id'       => $studentId,
            'contact_verified' => true,
        ]);
        $this->assertJsonResponse($response, 200, ['role', 'account_id']);

        $this->assertNull($this->fetchOne('student_supporter_assignments', [
            'student_id' => $studentId,
            'is_active'  => 1,
        ]));
    }

    public function testTelegramRegisterAutoAssignsUniqueSupporter(): void
    {
        $supporterId = 9730005;
        $this->seedSupporter($supporterId, 'Register Direction', 'تجربی تست', 7, [
            ['field' => 'تجربی تست', 'grade' => 7],
        ]);

        $tgId = 883000010;
        $ticket = $this->ticketFor($tgId);

        $response = $this->handleRequest($this->jsonRequest('POST', '/api/v1/auth/telegram/register', [
            'ticket'     => $ticket,
            'phone'      => '09973300101',
            'password'   => 'password123',
            'nationalId' => '1234500101',
            'grade'      => 7,
            'field'      => 'تجربی تست',
            'name'       => 'Register Auto',
        ], ['Origin' => 'http://localhost']));
        $this->assertCreatedResponse($response, ['token', 'user']);

        $student = $this->fetchOne('students', ['phone' => '09973300101']);
        $this->assertNotNull($student);

        $row = $this->fetchOne('student_supporter_assignments', [
            'student_id' => $student['id'],
            'is_active'  => 1,
        ]);
        $this->assertNotNull($row);
        $this->assertSame($supporterId, (int) $row['supporter_id']);
    }
}
