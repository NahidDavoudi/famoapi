<?php

namespace Tests\Modules\Supporters;

use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class SupporterContactTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->db->exec("DELETE FROM student_supporter_assignments WHERE supporter_id BETWEEN 9700100 AND 9700199");
        $this->db->exec("DELETE FROM supporters WHERE id BETWEEN 9700100 AND 9700199");
    }

    private function admin(string $method, string $uri, array $body = []): ResponseInterface
    {
        return $this->handleRequest(
            $this->adminRequest($method, $uri, $body, ['Origin' => 'http://localhost'])
        );
    }

    private function seedSupporterWithoutPhone(int $id, string $name): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO supporters (id, name, grade, field, phone, is_active) VALUES (?, ?, ?, ?, NULL, 1)'
        );
        $stmt->execute([$id, $name, 12, 'ریاضی']);
    }

    public function testMissingPhoneListAndPhoneUpdate(): void
    {
        $this->seedSupporterWithoutPhone(9700101, 'No Phone Supporter');

        $list = $this->admin('GET', '/api/v1/supporters/missing-phone');
        $listData = $this->assertJsonResponse($list, 200);
        $ids = array_column($listData['data'], 'id');
        $this->assertContains(9700101, $ids);

        $update = $this->admin('PUT', '/api/v1/supporters/9700101', [
            'phone'     => '۰۹۱۲۳۴۵۶۷۸۹',
            'is_active' => 0,
        ]);
        $updateData = $this->assertJsonResponse($update, 200, ['phone', 'is_active']);

        $this->assertSame('09123456789', $updateData['data']['phone']);
        $this->assertSame(0, (int) $updateData['data']['is_active']);

        $row = $this->fetchOne('supporters', ['id' => 9700101]);
        $this->assertSame('09123456789', $row['phone']);
        $this->assertSame(0, (int) $row['is_active']);
    }

    public function testPhoneUpdateRejectsInvalidNumber(): void
    {
        $this->seedSupporterWithoutPhone(9700102, 'Bad Phone Supporter');

        $response = $this->admin('PUT', '/api/v1/supporters/9700102', ['phone' => '123']);

        // SupporterController::update maps service exceptions to UPDATE_ERROR.
        $this->assertJsonResponse($response, 422, null, 'UPDATE_ERROR');
    }
}
