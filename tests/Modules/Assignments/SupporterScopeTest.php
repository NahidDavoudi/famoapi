<?php

namespace Tests\Modules\Assignments;

use App\Modules\Assignments\Assignment;
use App\Modules\Assignments\AutoAssignmentService;
use App\Modules\Supporters\Supporter;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class SupporterScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->db->exec("DELETE FROM student_supporter_assignments WHERE supporter_id BETWEEN 9720000 AND 9720099");
        $this->db->exec("DELETE FROM student_supporter_assignments WHERE student_id IN (SELECT id FROM students WHERE phone LIKE '09973200%')");
        $this->db->exec("DELETE FROM supporter_scopes WHERE supporter_id BETWEEN 9720000 AND 9720099");
        $this->db->exec("DELETE FROM supporters WHERE id BETWEEN 9720000 AND 9720099");
        $this->db->exec("DELETE FROM users WHERE username LIKE '09973200%'");
        $this->db->exec("DELETE FROM students WHERE phone LIKE '09973200%'");
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

    private function admin(string $method, string $uri, array $body = []): ResponseInterface
    {
        return $this->handleRequest(
            $this->adminRequest($method, $uri, $body, ['Origin' => 'http://localhost'])
        );
    }

    public function testInitialFillMatchesAllScopesOfOneSupporter(): void
    {
        $supporterId = 9720001;
        $this->seedSupporter($supporterId, 'Multi Scope', 'ریاضی', 7, [
            ['field' => 'ریاضی', 'grade' => 7],
            ['field' => 'ریاضی', 'grade' => 8],
            ['field' => 'ریاضی', 'grade' => 9],
        ]);

        $grade7 = $this->seedStudent(['phone' => '09973200071', 'name' => 'Scope G7', 'field' => 'ریاضی', 'grade' => 7]);
        $grade8 = $this->seedStudent(['phone' => '09973200081', 'name' => 'Scope G8', 'field' => 'ریاضی', 'grade' => 8]);
        $grade9 = $this->seedStudent(['phone' => '09973200091', 'name' => 'Scope G9', 'field' => 'ریاضی', 'grade' => 9]);
        $grade10 = $this->seedStudent(['phone' => '09973200101', 'name' => 'Scope G10', 'field' => 'علوم تست', 'grade' => 10]);

        $studentIds = [$grade7, $grade8, $grade9, $grade10];

        $dry = $this->admin('POST', '/api/v1/assignments/initial-fill', [
            'dry_run'     => true,
            'student_ids' => $studentIds,
        ]);
        $dryData = $this->assertJsonResponse($dry, 200, ['dry_run', 'summary', 'assigned']);
        $this->assertSame(3, $dryData['data']['summary']['assigned']);
        $this->assertSame(1, $dryData['data']['summary']['no_match']);
        $this->assertNull($this->fetchOne('student_supporter_assignments', [
            'student_id' => $grade7,
            'is_active'  => 1,
        ]));

        $apply = $this->admin('POST', '/api/v1/assignments/initial-fill', [
            'dry_run'     => false,
            'student_ids' => $studentIds,
        ]);
        $applyData = $this->assertJsonResponse($apply, 200, ['summary', 'assigned']);
        $this->assertSame(3, $applyData['data']['summary']['assigned']);

        foreach ([$grade7, $grade8, $grade9] as $studentId) {
            $row = $this->fetchOne('student_supporter_assignments', [
                'student_id' => $studentId,
                'is_active'  => 1,
            ]);
            $this->assertNotNull($row);
            $this->assertSame($supporterId, (int) $row['supporter_id']);
        }

        $this->assertNull($this->fetchOne('student_supporter_assignments', [
            'student_id' => $grade10,
            'is_active'  => 1,
        ]));

        $this->assertCount(3, Supporter::findScopes($supporterId));
    }

    public function testAutoAssignSupporterScopeIsIdempotentAndSkipsAssigned(): void
    {
        $supporterId = 9720002;
        $this->seedSupporter($supporterId, 'Scope Supporter', 'زبان تست', 10, [
            ['field' => 'زبان تست', 'grade' => 10],
            ['field' => 'زبان تست', 'grade' => 11],
        ]);

        $s10 = $this->seedStudent(['phone' => '09973200110', 'name' => 'Auto 10', 'field' => 'زبان تست', 'grade' => 10]);
        $s11 = $this->seedStudent(['phone' => '09973200111', 'name' => 'Auto 11', 'field' => 'زبان تست', 'grade' => 11]);

        $preAssigned = $this->seedStudent(['phone' => '09973200112', 'name' => 'Auto Pre', 'field' => 'زبان تست', 'grade' => 10]);
        $this->seedSupporter(9720009, 'Earlier Supporter', 'زبان تست', 10, [['field' => 'زبان تست', 'grade' => 10]]);
        Assignment::insert($preAssigned, 9720009, null, 'pre-existing');

        $service = new AutoAssignmentService();
        $first = $service->assignSupporterScope($supporterId);
        $this->assertSame(2, $first['assigned']);
        $this->assertContains($s10, $first['student_ids']);
        $this->assertContains($s11, $first['student_ids']);

        $second = $service->assignSupporterScope($supporterId);
        $this->assertSame(0, $second['assigned']);

        $active = Assignment::findActiveByStudent($preAssigned);
        $this->assertNotNull($active);
        $this->assertSame(9720009, (int) $active['supporter_id']);
    }

    public function testAutoAssignStudentUniqueAmbiguousAndZeroMatches(): void
    {
        $this->seedSupporter(9720003, 'Unique Match', 'هنر تست', 9, [['field' => 'هنر تست', 'grade' => 9]]);
        $unique = $this->seedStudent(['phone' => '09973200120', 'name' => 'Unique Student', 'field' => 'هنر تست', 'grade' => 9]);

        $noMatch = $this->seedStudent(['phone' => '09973200121', 'name' => 'No Match Student', 'field' => 'تاریخ تست', 'grade' => 9]);

        $this->seedSupporter(9720004, 'Ambiguous A', 'عمومی تست', 9, [['field' => 'عمومی تست', 'grade' => 9]]);
        $this->seedSupporter(9720005, 'Ambiguous B', 'عمومی تست', 9, [['field' => 'عمومی تست', 'grade' => 9]]);
        $ambiguous = $this->seedStudent(['phone' => '09973200122', 'name' => 'Ambiguous Student', 'field' => 'عمومی تست', 'grade' => 9]);

        $service = new AutoAssignmentService();

        $uniqueResult = $service->assignStudent($unique);
        $this->assertSame(1, $uniqueResult['assigned']);
        $this->assertSame(9720003, $uniqueResult['supporter_id']);

        $noMatchResult = $service->assignStudent($noMatch);
        $this->assertSame('no_match', $noMatchResult['status']);
        $this->assertSame(0, $noMatchResult['assigned']);

        $ambiguousResult = $service->assignStudent($ambiguous);
        $this->assertSame('ambiguous', $ambiguousResult['status']);
        $this->assertSame(0, $ambiguousResult['assigned']);
        $this->assertNull($this->fetchOne('student_supporter_assignments', [
            'student_id' => $ambiguous,
            'is_active'  => 1,
        ]));
    }

    public function testSupporterUpdateReplacesScopesViaApi(): void
    {
        $supporterId = 9720006;
        $this->seedSupporter($supporterId, 'Scoped Update', 'ریاضی', 7, [
            ['field' => 'ریاضی', 'grade' => 7],
        ]);

        $update = $this->admin('PUT', '/api/v1/supporters/' . $supporterId, [
            'scopes' => [
                ['field' => 'ریاضی', 'grade' => 7],
                ['field' => 'تجربی', 'grade' => 8],
            ],
        ]);
        $this->assertJsonResponse($update, 200);

        $scopes = Supporter::findScopes($supporterId);
        $this->assertCount(2, $scopes);

        $legacy = $this->admin('PUT', '/api/v1/supporters/' . $supporterId, [
            'field' => 'انسانی',
            'grade' => 9,
        ]);
        $this->assertJsonResponse($legacy, 200);

        $scopes = Supporter::findScopes($supporterId);
        $this->assertCount(1, $scopes);
        $this->assertSame('انسانی', $scopes[0]['field']);
        $this->assertSame(9, $scopes[0]['grade']);
    }

    public function testSupporterUpdateRejectsInvalidScope(): void
    {
        $supporterId = 9720007;
        $this->seedSupporter($supporterId, 'Bad Scope', 'ریاضی', 7, [
            ['field' => 'ریاضی', 'grade' => 7],
        ]);

        $response = $this->admin('PUT', '/api/v1/supporters/' . $supporterId, [
            'scopes' => [['field' => 'ریاضی', 'grade' => 99]],
        ]);

        $this->assertJsonResponse($response, 422, null, 'VALIDATION_ERROR');
    }
}
