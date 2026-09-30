<?php

namespace Tests\Modules\Assignments;

use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->db->exec("DELETE FROM student_supporter_assignments WHERE supporter_id BETWEEN 9700000 AND 9700999");
        $this->db->exec("DELETE FROM student_supporter_assignments WHERE student_id IN (SELECT id FROM students WHERE phone LIKE '09997777%')");
        $this->db->exec("DELETE FROM supporters WHERE id BETWEEN 9700000 AND 9700999");
        $this->db->exec("DELETE FROM users WHERE username LIKE '09997777%'");
        $this->db->exec("DELETE FROM students WHERE phone LIKE '09997777%'");
    }

    private function seedSupporter(int $id, string $name, string $field, int $grade, int $active = 1): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO supporters (id, name, grade, field, phone, chat_id, is_active)
             VALUES (?, ?, ?, ?, NULL, NULL, ?)'
        );
        $stmt->execute([$id, $name, $grade, $field, $active]);
    }

    private function admin(string $method, string $uri, array $body = []): ResponseInterface
    {
        return $this->handleRequest(
            $this->adminRequest($method, $uri, $body, ['Origin' => 'http://localhost'])
        );
    }

    public function testAssignReassignHistoryAndUnassign(): void
    {
        $studentId = $this->seedStudent([
            'phone' => '09997777001',
            'name'  => 'Assign Target',
            'field' => 'ریاضی',
            'grade' => 12,
        ]);
        $this->seedSupporter(9700001, 'Supporter A', 'ریاضی', 12);
        $this->seedSupporter(9700002, 'Supporter B', 'ریاضی', 12);

        $assignA = $this->admin('POST', '/api/v1/assignments', [
            'student_id'   => $studentId,
            'supporter_id' => 9700001,
        ]);
        $dataA = $this->assertJsonResponse($assignA, 200, ['student_id', 'supporter_id']);
        $this->assertSame(9700001, $dataA['data']['supporter_id']);

        $assignB = $this->admin('POST', '/api/v1/assignments', [
            'student_id'   => $studentId,
            'supporter_id' => 9700002,
        ]);
        $dataB = $this->assertJsonResponse($assignB, 200, ['supporter_id']);
        $this->assertSame(9700002, $dataB['data']['supporter_id']);

        $history = $this->admin('GET', "/api/v1/assignments/history/{$studentId}");
        $historyData = $this->assertJsonResponse($history, 200, ['history']);
        $this->assertCount(2, $historyData['data']['history']);

        $active = array_values(array_filter(
            $historyData['data']['history'],
            static fn (array $row) => (int) $row['is_active'] === 1
        ));
        $this->assertCount(1, $active);
        $this->assertSame(9700002, (int) $active[0]['supporter_id']);

        $unassign = $this->admin('DELETE', "/api/v1/assignments/{$studentId}");
        $unassignData = $this->assertJsonResponse($unassign, 200, ['supporter_id']);
        $this->assertNull($unassignData['data']['supporter_id']);
    }

    public function testInitialFillDryRunDoesNotWriteAndApplyDoes(): void
    {
        $studentId = $this->seedStudent([
            'phone' => '09997777002',
            'name'  => 'Fill Target',
            'field' => 'تجربی',
            'grade' => 11,
        ]);
        $this->seedSupporter(9700003, 'Only Match', 'تجربی', 11);

        $dry = $this->admin('POST', '/api/v1/assignments/initial-fill', [
            'dry_run'     => true,
            'student_ids' => [$studentId],
        ]);
        $dryData = $this->assertJsonResponse($dry, 200, ['dry_run', 'summary', 'assigned']);
        $this->assertTrue($dryData['data']['dry_run']);
        $this->assertSame(1, $dryData['data']['summary']['assigned']);

        $this->assertNull($this->fetchOne('student_supporter_assignments', [
            'student_id' => $studentId,
            'is_active'  => 1,
        ]));

        $apply = $this->admin('POST', '/api/v1/assignments/initial-fill', [
            'dry_run'     => false,
            'student_ids' => [$studentId],
        ]);
        $applyData = $this->assertJsonResponse($apply, 200, ['summary']);
        $this->assertSame(1, $applyData['data']['summary']['assigned']);

        $row = $this->fetchOne('student_supporter_assignments', [
            'student_id' => $studentId,
            'is_active'  => 1,
        ]);
        $this->assertNotNull($row);
        $this->assertSame(9700003, (int) $row['supporter_id']);
    }

    public function testInitialFillReportsAmbiguousStudents(): void
    {
        $studentId = $this->seedStudent([
            'phone' => '09997777003',
            'name'  => 'Ambiguous Target',
            'field' => 'انسانی',
            'grade' => 12,
        ]);
        $this->seedSupporter(9700004, 'Humanities A', 'انسانی', 12);
        $this->seedSupporter(9700005, 'Humanities B', 'انسانی', 12);

        $response = $this->admin('POST', '/api/v1/assignments/initial-fill', [
            'dry_run'     => true,
            'student_ids' => [$studentId],
        ]);
        $data = $this->assertJsonResponse($response, 200, ['ambiguous']);

        $ambiguousIds = array_column($data['data']['ambiguous'], 'student_id');
        $this->assertContains($studentId, $ambiguousIds);
    }

    public function testUnassignedStudentsCanBeListed(): void
    {
        $studentId = $this->seedStudent([
            'phone' => '09997777004',
            'name'  => 'Unassigned Person',
        ]);

        $response = $this->admin('GET', '/api/v1/assignments/students?status=unassigned&search=Unassigned%20Person');
        $data = $this->assertJsonResponse($response, 200);

        $ids = array_column($data['data'], 'id');
        $this->assertContains($studentId, $ids);
        foreach ($data['data'] as $row) {
            $this->assertNull($row['supporter_id']);
        }
    }
}
